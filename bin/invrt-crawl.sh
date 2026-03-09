#!/bin/bash

echo "🕸️ Crawling $INVRT_URL to depth $INVRT_DEPTH_TO_CRAWL"

cd ./invrt/data/clones

wget \
      --level=$DEPTH_TO_CRAWL \
      --spider \
      --recursive \
      --force-html \
      --max-redirect=2 \
      --user-agent=invrt/crawler \
      --exclude-directories=/sites/default/files \
      --execute robots=off \
      $INVRT_URL 2>&1 | tee -a $INVRT_CRAWL_LOG_DIR \
      | grep -B 3 "\[text/html\]" \
      | grep $INVRT_URL \
      | awk "/--/{gsub(\"$INVRT_URL\", \"\", \$3); print \$3}" \
      | sort \
      | uniq \
      > ./invrt/data/crawled_urls.txt

echo "Crawling completed. Results saved to ./invrt/data/crawled_urls.txt"