#!/bin/bash


echo "🕸️ Crawling '$INVRT_ENVIRONMENT' environment ($INVRT_URL) with profile: '$INVRT_PROFILE' to depth: $INVRT_MAX_CRAWL_DEPTH, max pages: $INVRT_MAX_PAGES"

mkdir -p $INVRT_DATA_DIR/clones $INVRT_DATA_DIR/logs
rm -rf $INVRT_DATA_DIR/clones/* $INVRT_DATA_DIR/logs/*

INVRT_DOMAIN=$(echo "$INVRT_URL" | sed -e 's|^[^/]*//||' -e 's|/.*$||')

if [ -f "$INVRT_DIRECTORY/exclude_urls.txt" ]; then
      EXCLUDE_URLS=$(cat $INVRT_DIRECTORY/exclude_urls.txt | grep -v '^\s*#'| tr '\n' ',' | sed 's/,$//')
      echo "Excluding URLs: $EXCLUDE_URLS"
else
      EXCLUDE_URLS='/files/sites,/user/logout';
      echo "No exclude_urls.txt file found at $INVRT_DIRECTORY/exclude_urls.txt. Default URLs will be excluded from crawling: $EXCLUDE_URLS"
fi

wget \
      --level=$INVRT_MAX_CRAWL_DEPTH \
      --recursive \
      --max-redirect=3 \
      --user-agent=invrt/crawler \
      --load-cookies=$INVRT_DATA_DIR/cookies.txt \
      --ignore-length \
      --exclude-directories="$EXCLUDE_URLS" \
      --ignore-length \
      --no-verbose \
      --no-check-certificate \
      --reject=css,js,woff,jpg,png,gif,svg \
      --directory-prefix=$INVRT_DATA_DIR/clones \
      --no-directories \
      --no-host-directories \
      --delete-after \
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