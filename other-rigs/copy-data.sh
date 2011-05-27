#!/bin/sh
# use this script to copy over data from other rigs.
#
# the directory structure should be like:
#
# other-rigs/<dir-for-rig>/config.json
# other-rigs/<dir-for-rig>/rrd/<rrd files from rig>
#
# example scp command to achieve this:
# mkdir machine && scp -r user@machine:rug-monitor/rrd user@machine:rug-monitor/config.json machine/
#
# the directory name is not important, but each rig should have its own directory.

