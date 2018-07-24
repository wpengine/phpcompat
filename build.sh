#!/bin/bash
if [ ! -d "build" ]; then
	svn co https://plugins.svn.wordpress.org/php-compatibility-checker build
else
	pushd build
	svn up
	popd
fi

composer install --no-dev
rsync \
	-avuP \
	--delete \
	--exclude="Tests" \
	--include='wpengine-phpcompat.php' \
	--include='uninstall.php' \
	--include='load-files.php' \
	--include='readme.txt' \
	--include='src' \
	--include="src/**" \
	--include='vendor' \
	--include='vendor/**' \
	--include='php52' \
	--include='php52/**' \
	--exclude="*" \
	./ ./build/trunk

pushd ./build/trunk
zip -r phpcompat.zip .
popd
rm ./phpcompat.zip
mv ./build/trunk/phpcompat.zip ./
