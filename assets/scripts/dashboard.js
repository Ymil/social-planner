"use strict";!function(){var a,d,l,i;"undefined"!=typeof wp&&(a=wp.i18n.__,null!==(d=document.querySelector("#social-planner-dashboard > .inside"))&&void 0!==window.socialPlannerDashboard&&(l=window.socialPlannerDashboard||[],i=function(e){var n=document.createElement("div");n.classList.add("social-planner-task");var t,a=(t=e,(d=document.createElement("div")).classList.add("social-planner-header"),t.scheduled&&((a=document.createElement("strong")).textContent=t.scheduled,d.appendChild(a)),t.postlink&&t.editlink&&((a=document.createElement("a")).setAttribute("href",t.editlink),a.textContent=t.postlink,d.appendChild(a)),d);n.appendChild(a);var d,l,a,a=(d=e,(a=document.createElement("div")).classList.add("social-planner-content"),d.excerpt&&((l=document.createElement("div")).classList.add("social-planner-excerpt"),l.textContent=d.excerpt,a.appendChild(l)),d.thumbnail&&((l=document.createElement("img")).classList.add("social-planner-thumbnail"),l.setAttribute("src",d.thumbnail),a.appendChild(l)),a);n.appendChild(a);e=function(e){var n=document.createElement("div");n.classList.add("social-planner-targets"),e.networks=e.networks||[];for(var t=0;t<e.networks.length;t++){var a=document.createElement("span");a.classList.add("social-planner-network"),a.textContent=e.networks[t],n.appendChild(a)}return n}(e);return n.appendChild(e),n},function(){if(!l)return function(e,n){var t=document.createElement("p");for(t.classList.add("social-planner-warning"),t.textContent=n;e.firstChild;)e.removeChild(e.lastChild);e.appendChild(t)}(d,a("Nothing planned.","social-planner"));var e=document.createElement("div");e.classList.add("social-planner-list"),d.appendChild(e);for(var n=0;n<l.length;n++){var t=i(l[n]);e.appendChild(t)}}()))}();