#!/bin/bash
# ref: https://sigmoidal.io/automatic-code-quality-checks-with-git-hooks/

GIT_DIR=$(git rev-parse --git-dir)

echo "Installing hooks..."
ln -s pre-commit.sh $GIT_DIR/hooks/pre-commit
echo "Done!"
