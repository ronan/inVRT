#! /usr/bin/env bash

aws s3 sync $1 \
  s3://wymbrdhrdcq-invrt-report \
  --endpoint-url https://6278803b2fd8bcbd6bcce4ac05247ce3.r2.cloudflarestorage.com \
  --no-verify-ssl \
  --debug
