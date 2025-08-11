#!/bin/bash

# This is a simple script to pull down the specified version of editoria11y from github

GIT_REF="2.3.x"
#GIT_REF="2.3.12-dev"

mkdir -p tmp/
cd tmp/
git clone git@github.com:itmaybejj/editoria11y.git .
git checkout $GIT_REF
rm -rf ../library/js
rm -rf ../library/css
rm -rf ../library/dist
mv js ../library/js
mv css ../library/css

mv dist ../library/dist
cd ../
rm -rf tmp
