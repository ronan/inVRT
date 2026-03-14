#!/bin/bash


echo "🕸️ Crawling $INVRT_URL with profile $INVRT_PROFILE to depth $INVRT_DEPTH_TO_CRAWL max $INVRT_MAX_PAGES"

mkdir -p $INVRT_DATA_DIR/clones $INVRT_DATA_DIR/logs
rm -rf $INVRT_DATA_DIR/clones/* $INVRT_DATA_DIR/logs/*
# cd $INVRT_DATA_DIR/clones

INVRT_DOMAIN=$(echo "$INVRT_URL" | sed -e 's|^[^/]*//||' -e 's|/.*$||')

wget \
      --level=$INVRT_DEPTH_TO_CRAWL \
      --recursive \
      --max-redirect=2 \
      --user-agent=invrt/crawler \
      --load-cookies=$INVRT_DATA_DIR/cookies.txt \
      --ignore-length \
      --exclude-directories='/files,/user/logout' \
      --ignore-length \
      --no-verbose \
      --no-host-directories \
      --no-directories \
      --directory-prefix=$INVRT_DATA_DIR/clones \
      --execute robots=off \
      --domains=$INVRT_DOMAIN \
      "$INVRT_URL" 2>&1 \
      | tee $INVRT_DATA_DIR/logs/crawl.log \
      | grep "URL:$INVRT_URL" \
      | awk "//{gsub(\"URL:$INVRT_URL\", \"\"); print \$3}" \
      | sort \
      | uniq \
      > $INVRT_DATA_DIR/crawled_urls.txt

echo "Crawling completed. Results saved to $INVRT_DATA_DIR/crawled_urls.txt"