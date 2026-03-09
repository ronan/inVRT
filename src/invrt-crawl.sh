#!/bin/bash


echo "🕸️ Crawling $INVRT_URL with profile $INVRT_PROFILE to depth $INVRT_DEPTH_TO_CRAWL max $INVRT_MAX_PAGES"

cd $INVRT_DIRECTORY/data/clones

wget \
      --level=$INVRT_DEPTH_TO_CRAWL \
      --recursive \
      --force-html \
      --max-redirect=2 \
      --user-agent=invrt/crawler \
      --header="Cookie: $INVRT_COOKIE" \
      --exclude-directories=/files/*,/user/logout \
      --execute robots=off \
      $INVRT_URL 2>&1 | tee -a $INVRT_CRAWL_LOG_DIR \
      | grep -B 3 "\[text/html\]" \
      | grep $INVRT_URL \
      | awk "/--/{gsub(\"$INVRT_URL\", \"\", \$3); print \$3}" \
      | sort \
      | uniq \
      > $INVRT_DIRECTORY/crawled_urls.txt

echo "Crawling completed. Results saved to $INVRT_DIRECTORY/crawled_urls.txt"