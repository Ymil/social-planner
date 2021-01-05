<?php
/**
 * Metabox class
 *
 * @package social-planner
 * @author  Anton Lukin
 */

namespace Social_Planner;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Metabox class
 */
class Metabox {
	/**
	 * Metabox ID.
	 *
	 * @var string
	 */
	const METABOX_ID = 'social-planner-metabox';

	/**
	 * Cancel ajax action.
	 */
	const AJAX_ACTION = 'social-planner-action';

	/**
	 * Post meta key to store tasks.
	 *
	 * @var string
	 */
	const META_TASKS = '_social_planner_tasks';

	/**
	 * Post meta key to store results.
	 *
	 * @var string
	 */
	const META_RESULTS = '_social_planner_results';

	/**
	 * Metabox nonce field
	 */
	const METABOX_NONCE = 'social_planner_metabox_nonce';

	/**
	 * Add hooks to handle metabox.
	 */
	public static function add_hooks() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_metabox' ) );
		add_action( 'save_post', array( __CLASS__, 'save_metabox' ), 10, 2 );

		// Metabox AJAX actions.
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'process_ajax' ) );
	}

	/**
	 * Add plugin page in WordPress menu.
	 */
	public static function add_metabox() {
		add_meta_box(
			self::METABOX_ID,
			esc_html__( 'Social Planner', 'social-planner' ),
			array( __CLASS__, 'display_metabox' ),
			self::get_post_types(),
			'advanced'
		);

		// Add required assets and objects.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Display metabox.
	 */
	public static function display_metabox() {
		do_action( 'social_planner_metabox_before' );

		printf(
			'<p class="hide-if-js">%s</p>',
			esc_html__( 'This metabox requires JavaScript. Enable it in your browser settings, please.', 'social-planner' ),
		);

		wp_nonce_field( 'metabox', self::METABOX_NONCE );
	}

	/**
	 * Save metabox fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_metabox( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST[ self::METABOX_NONCE ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST[ self::METABOX_NONCE ] ), 'metabox' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::META_TASKS ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$tasks = (array) wp_unslash( $_POST[ self::META_TASKS ] );

		// Sanitize raw post data.
		$tasks = self::sanitize_tasks( $tasks );

		// Try to schedule something.
		$tasks = Scheduler::schedule_tasks( $tasks, $post );

		self::update_tasks( $post_id, $tasks );

		// Remove from meta results deleted tasks.
		self::sync_results( $post_id, $tasks );
	}

	/**
	 * Enqueue metabox styles.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public static function enqueue_styles( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! in_array( $screen->post_type, self::get_post_types(), true ) ) {
			return;
		}

		wp_enqueue_style(
			'social-planner-metabox',
			SOCIAL_PLANNER_URL . '/assets/styles/metabox.min.css',
			array(),
			SOCIAL_PLANNER_VERSION,
			'all'
		);
	}

	/**
	 * Enqueue metabox scripts.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public static function enqueue_scripts( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! in_array( $screen->post_type, self::get_post_types(), true ) ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'social-planner-metabox',
			SOCIAL_PLANNER_URL . '/assets/scripts/metabox.min.js',
			array( 'wp-i18n' ),
			SOCIAL_PLANNER_VERSION,
			true
		);

		wp_localize_script( 'social-planner-metabox', 'socialPlannerMetabox', self::create_script_object() );
	}

	/**
	 * Process metabox ajax action.
	 */
	public static function process_ajax() {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		if ( empty( $_POST['handler'] ) || empty( $_POST['post'] ) ) {
			wp_send_json_error();
		}

		if ( 'cancel' === $_POST['handler'] ) {
			$post_id = absint( $_POST['post'] );

			if ( empty( $_POST['key'] ) ) {
				wp_send_json_error();
			}

			$key = sanitize_key( $_POST['key'] );

			// Nevermind if the task is not exists.
			Scheduler::unschedule_task( $key, $post_id );
		}

		wp_send_json_success();
	}

	/**
	 * Sanitize post tasks.
	 *
	 * @param array $tasks Post data from admin-side metabox.
	 */
	private static function sanitize_tasks( $tasks ) {
		$sanitized = array();

		// Get providers from settings.
		$providers = Settings::get_providers();

		foreach ( $tasks as $key => $task ) {
			$key = sanitize_key( $key );

			if ( ! empty( $task['targets'] ) ) {
				foreach ( (array) $task['targets'] as $target ) {
					if ( ! array_key_exists( $target, $providers ) ) {
						continue;
					}

					$sanitized[ $key ]['targets'][] = $target;
				}
			}

			if ( ! empty( $task['excerpt'] ) ) {
				$sanitized[ $key ]['excerpt'] = sanitize_textarea_field( $task['excerpt'] );
			}

			if ( ! empty( $task['thumbnail'] ) ) {
				$sanitized[ $key ]['thumbnail'] = sanitize_text_field( $task['thumbnail'] );
			}

			if ( ! empty( $task['preview'] ) ) {
				$sanitized[ $key ]['preview'] = 1;
			}

			if ( ! empty( $task['attachment'] ) ) {
				$sanitized[ $key ]['attachment'] = absint( $task['attachment'] );
			}

			if ( ! empty( $task['date'] ) ) {
				$sanitized[ $key ]['date'] = sanitize_text_field( $task['date'] );

				if ( isset( $task['hour'] ) ) {
					$sanitized[ $key ]['hour'] = sanitize_text_field( $task['hour'] );
				}

				if ( isset( $task['minute'] ) ) {
					$sanitized[ $key ]['minute'] = sanitize_text_field( $task['minute'] );
				}
			}
		}

		/**
		 * Filter tasks while saving metabox.
		 *
		 * @param array $tasks List of tasks.
		 */
		return apply_filters( 'social_planner_sanitize_tasks', array_filter( $sanitized ) );
	}

	/**
	 * Get and filter tasks from post meta by post ID.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_tasks( $post_id ) {
		$tasks = get_post_meta( $post_id, self::META_TASKS, true );

		if ( empty( $tasks ) ) {
			$tasks = array();
		}

		/**
		 * Filter tasks from post meta by post ID.
		 *
		 * @param array $tasks   List of tasks from post meta.
		 * @param int   $post_id Post ID.
		 */
		return apply_filters( 'social_planner_get_tasks', $tasks, $post_id );
	}

	/**
	 * Filter and update tasks in post meta.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $tasks   List of tasks.
	 */
	public static function update_tasks( $post_id, $tasks ) {
		/**
		 * Filter tasks before update post meta.
		 *
		 * @param array $tasks   List of tasks from post meta.
		 * @param int   $post_id Post ID.
		 */
		$tasks = apply_filters( 'social_planner_update_tasks', $tasks, $post_id );

		update_post_meta( $post_id, self::META_TASKS, $tasks );
	}

	/**
	 * Get and filter results from post meta.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_results( $post_id ) {
		$results = get_post_meta( $post_id, self::META_RESULTS, true );

		if ( empty( $results ) ) {
			$results = array();
		}

		/**
		 * Results tasks from post meta by post ID.
		 *
		 * @param array $results List of results from post meta.
		 * @param int   $post_id Post ID.
		 */
		return apply_filters( 'social_planner_get_results', $results, $post_id );
	}

	/**
	 * Filter and update results in post meta.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $results List of task results.
	 */
	public static function update_results( $post_id, $results ) {
		/**
		 * Filter results before update post meta.
		 *
		 * @param array $results List of tasks from post meta.
		 * @param int   $post_id Post ID.
		 */
		$tasks = apply_filters( 'social_planner_update_results', $results, $post_id );

		update_post_meta( $post_id, self::META_RESULTS, $results );
	}

	/**
	 * Sync results with tasks for certain post id.
	 * This method is used to delete results when deleting tasks from metabox.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $tasks   List of tasks.
	 */
	public static function sync_results( $post_id, $tasks ) {
		$results = self::get_results( $post_id );

		foreach ( $results as $key => $result ) {
			if ( ! array_key_exists( $key, $tasks ) ) {
				unset( $results[ $key ] );
			}
		}

		self::update_results( $post_id, $results );
	}

	/**
	 * Create scripts object to inject with metabox.
	 *
	 * @return array
	 */
	private static function create_script_object() {
		$post = get_post();

		$object = array(
			'meta'      => self::META_TASKS,
			'action'    => self::AJAX_ACTION,
			'nonce'     => wp_create_nonce( self::AJAX_ACTION ),

			'tasks'     => self::prepare_tasks( $post->ID ),
			'results'   => self::prepare_results( $post->ID ),
			'offset'    => self::get_time_offset(),
			'calendar'  => self::get_calendar_days(),
			'providers' => self::prepare_providers(),
		);

		$object['schedules'] = self::get_schedules( $post->ID, $object['tasks'] );

		/**
		 * Filter metabox scripts object.
		 *
		 * @param array $object Array of metabox script object.
		 */
		return apply_filters( 'social_planner_metabox_object', $object );
	}

	/**
	 * Get and prepare to show tasks.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array
	 */
	private static function prepare_tasks( $post_id ) {
		$tasks = self::get_tasks( $post_id );

		foreach ( $tasks as $key => $task ) {
			if ( ! empty( $task['thumbnail'] ) ) {
				$tasks[ $key ]['thumbnail'] = esc_url( $task['thumbnail'] );
			}

			if ( ! empty( $task['attachment'] ) ) {
				$tasks[ $key ]['attachment'] = absint( $task['attachment'] );
			}
		}

		return $tasks;
	}

	/**
	 * Get a array of types for which the metabox is displayed.
	 *
	 * @return array
	 */
	private static function get_post_types() {
		$post_types = get_post_types(
			array(
				'public' => true,
			)
		);

		unset( $post_types['attachment'] );

		/**
		 * Filter metabox post types.
		 *
		 * @param array $post_types Array of post types for which the metabox is displayed.
		 */
		return apply_filters( 'social_planner_post_types', array_values( $post_types ) );
	}

	/**
	 * Get a list of localized nearest dates.
	 *
	 * @return array
	 */
	private static function get_calendar_days() {
		$calendar = array();

		// Get UTC timestamp.
		$current_time = time();

		/**
		 * Filter number of future days in schedule select box.
		 *
		 * @param int $days_count Number of days in task calendar select box.
		 */
		$days_number = apply_filters( 'social_planner_calendar_days', 20 );

		for ( $i = 0; $i < $days_number; $i++ ) {
			$future_time = strtotime( "+ $i days", $current_time );
			$future_date = wp_date( 'Y-m-d', $future_time );

			// Generate calendar with local human-readable date.
			$calendar[ $future_date ] = wp_date( 'j F, l', $future_time );
		}

		return $calendar;
	}

	/**
	 * Get and prepare required providers settings.
	 *
	 * @return array
	 */
	private static function prepare_providers() {
		$prepared = array();

		// Get providers list from options.
		$providers = Settings::get_providers();

		foreach ( $providers as $key => $provider ) {
			$class = Core::get_network_class( $key );

			// Get network label by class.
			$label = Core::get_network_label( $class );

			if ( ! empty( $provider['title'] ) ) {
				$label = $label . ': ' . $provider['title'];
			}

			$prepared[ $key ]['label'] = esc_attr( $label );

			if ( method_exists( $class, 'get_limit' ) ) {
				$prepared[ $key ]['limit'] = absint( $class::get_limit() );
			}
		}

		return $prepared;
	}

	/**
	 * Get and prepare tasks results.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array
	 */
	private static function prepare_results( $post_id ) {
		$results = self::get_results( $post_id );

		foreach ( $results as $key => $result ) {
			if ( empty( $result['sent'] ) ) {
				continue;
			}

			$results[ $key ]['sent'] = wp_date( Core::time_format(), $result['sent'] );
		}

		return $results;
	}

	/**
	 * Get time offset.
	 *
	 * Time offset in seconds between UTC and server time.
	 * It will be used in admin-side metabox to choose the time for planning.
	 * Can be filtered to add your own preferred publish delay offset.
	 *
	 * @return int
	 */
	private static function get_time_offset() {
		$offset = timezone_offset_get( wp_timezone(), date_create( 'now' ) );

		/**
		 * Filter time offset in seconds from UTC.
		 */
		return apply_filters( 'social_planner_time_offset', $offset );
	}

	/**
	 * Get list of schedules for current post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $tasks   List of tasks from post meta.
	 */
	private static function get_schedules( $post_id, $tasks ) {
		$schedules = array();

		foreach ( $tasks as $key => $task ) {
			$scheduled = Scheduler::get_scheduled_time( $key, $post_id );

			if ( $scheduled ) {
				$schedules[ $key ] = wp_date( Core::time_format(), $scheduled );
			}
		}

		return $schedules;
	}
}