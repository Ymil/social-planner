npm run build

cp src/*.php dist
cp -r src/networks dist/
cp -r src/classes dist/
cp -r src/templates dist/

cp -r dist social-planner
zip -r social-planner.zip social-planner
rm -rf social-planner