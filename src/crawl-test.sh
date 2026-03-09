
INVRT_URL=https://vrt-postdocs-idp-bd.pantheonsite.io/ \
INVRT_DEPTH_TO_CRAWL=3 \
INVRT_MAX_PAGES=10 \
INVRT_PROFILE=sponsor \
INVRT_DEVICE=desktop \
INVRT_CRAWL_LOG_DIR=/workspaces/invrt/test/idp/.invrt/logs \
INVRT_COOKIE="SSESS3b970fec3cb0d0e8bf797eb840677d99=HtoIQZkt5G52J3q86zIpkxhlkv-a9sV9wshztpVNEgw; simplesamlphp_auth_returnto=https://vrt-postdocs-idp-bd.pantheonsite.io/; SimpleSAMLSessionID=8fdf34da1aad69d97d372fe3343298de" \
INVRT_DIRECTORY=/workspaces/invrt/test/idp/.invrt


wget \
      --level=$INVRT_DEPTH_TO_CRAWL \
      --spider \
      --recursive \
      --force-html \
      --no-verbose \
      --read-timeout=5 \
      --max-redirect=2 \
      --ignore-length \
      --user-agent=invrt/crawler \
      --header="Cookie: $INVRT_COOKIE" \
      --exclude-directories=/files,/user/logout \
      --execute robots=off \
      $INVRT_URL