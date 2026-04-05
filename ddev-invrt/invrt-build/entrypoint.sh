#!/bin/bash
#ddev-generated

routerIp=$(getent hosts ddev-router | awk '{ print $1 }')

# Add entries to /etc/hosts for each DDEV_HOSTNAME (all hostnames in the web container)
if [ -n "$routerIp" ]; then
  OIFS=$IFS
  IFS=','
  for i in $DDEV_HOSTNAME; do
    echo "Add to /etc/hosts: routerIp $i"
    sudo sh -c "echo $routerIp $i >> /etc/hosts"
  done
  IFS=$OIFS
fi

# remove already existing certs of ddev from certs file
sed -i '/^ddev\//d' "/etc/ca-certificates.conf"

# add all ddev certs
for entry in /usr/share/ca-certificates/ddev/*.crt
do
  b=ddev/$(basename "$entry")
  echo "$b" >> /etc/ca-certificates.conf
done

update-ca-certificates --fresh

sleep infinity
