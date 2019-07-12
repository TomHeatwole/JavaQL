#!/bin/bash

./u
if [ $? == 1 ]
then
    echo "Commit error: all unit tests must pass"
    exit 1;
fi
./db
if [ $? == 1 ]
then
    echo "Commit error: database tests must pass"
    exit 1;
fi
