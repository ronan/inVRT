#!/bin/bash

#ddev-generated

if [ $DDEV_PROJECT_TYPE == "drupal11" ]; then

  echo "\
# Drupal roles from $DDEV_PROJECT
"
  for role in $(ddev drush role:list --field=rid --filter="rid!=anonymous"); do
    echo "
$role:
  name: $(ddev drush role:list --filter=rid=$role --field=label)
  username: invrt-test-$role-user
  password: invrt-test-$role-password"
  done

elif [ $DDEV_PROJECT_TYPE == "backdrop" ]; then

  echo "# Backdrop roles from $DDEV_PROJECT
"
  for role in $(ddev bee roles-list | sed 's/\:.*//' | sed '/^$/d'); do
    shopt -s nocasematch
    if [ "$role" == "anonymous" ]; then
      continue
    fi
    echo "
$role:
  name: $role
  username: invrt-test-$role-user
  password: invrt-test-$role-password"
  done
elif [ $DDEV_PROJECT_TYPE == "wordpress" ]; then
  echo "# No profiles detected for wordpress"
fi
