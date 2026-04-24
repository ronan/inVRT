# Site Plan File format (yaml)

The site plan is a file that describes the site as a tree based on url paths:

It is designed to allow a list of site paths to be stored in a compact readble manner but also
optionally allow metadata for the page (such as page title and test settings) to be stored.

You can also apply test settings to entire branches of the tree as well as to a single page.

## Project

The `project` section contains basic information about the project being tested:

```
project:
    url: http://invrt.sh
    id: zzytgghxc
    title: inVRT — Visual Regression Testing for CMS Websites
    login_url: /user/login
```

## Pages

The pages that make up the site are listed in a 'pages' object at the top level.

A site with the following urls:

  - /
  - /about                                      # has a landing page and sub pages but no trailing slash
  - /about/contact
  - /about/history
  - /accounts/aabraham
  - /accounts/bbarnett
  - /past-events
  - /past-events/2021/                          # sub pages and a landing page with a trailing slash.
  - /past-events/2021/about.html
  - /past-events/2021/content/sessions.html
  - /past-events/2021/venue/eat.html            # note there is no /venue landing page 
  - /past-events/2021/venue/travel.html
  - /directory
  - /directory?page=1
  - /directory?page=2
  - /directory?page=3
  - /directory?page=4

Would become:

```yaml
pages:
    /:
    /about:
        :
        /contact:
        /history:
    /accounts:
        /aabraham:
        /bbarnet:
    /past-events:
        /2021:
            /:
            /about.html:
            /content/sessions.html:
            /venue:
                /eat.html:
                /travel.html:
    /directory:
        ?page=1:
        ?page=2:
        ?page=3:
        ?page=4:

```


This data can be further modified to include more information about each page as discovered during the site crawl.

Children path keys start with '/'.

The empty string key represents the 'landing' page for the parent page if that page does not end with a '/'. A single slash represents the same page if the url DOES end with a slash.


```yaml
pages:
    /:
        title:                    Home Page
    /about:
        # Empty key because the url is '/about' not '/about/'
        :                           # Empty key because the url is '/about' not '/about/'
            title:                  About Us
    /contact:
        code:                       404 Not found
    /history:
        title:                      About the Organization
    /accounts:
        /aabraham:                  Aziz Abraham
        /bbarnet:                   Barb Barnet
    /past-events:
        /2021:
            # '/' key because the url is '/past-events/2021/' not '/past-events/2021'
            /:                      Welcome to The 2021 Event
            /about.html:            About the 2021 Conference
            /content/sessions.html: This Year's Sessions
            /venue/:
                /eat.html:          Where to Eat
                /travel.html:       Getting Here
```

## Shorthand Notation

If a page in the tree does not have any metadata or children it can be represented by an empty object:

```yaml
pages:
    /:
    /about:
    /conact:
```

If the page has only a title, it can be represented by a single scalar string:

```yaml
pages:
    /:
        title:                        Home Page
        /about:                       About Us
        /conact:                      Contact Us
```

## Applying Settings to the Tree

Test settings can be customized per page or per branch by adding the config key to the object:

```yaml
pages:
    /:
        onready':                   home.onready.js
        timeout':                   60

```


## Flat Branches

If a path has multiple path segments but there are no intermediary landing pages, the whole path can be represented in the key:

```yaml
pages:
    /about:                         About Us
    /accounts/aabraham:             Aziz Abraham
    /accounts/bbarnet:              Barb Barnet
```

## The '/' and '' Keys

In the above example: `/about` is itself a testable page and also contains children while `/accounts` has children but is not itself a testable page.

For `/about` we need to be able to specify settings for the page itself as well as settings that apply to all it's children.

The key `/` represents the page that is served at the address represented by the parent object's key. The key '' represends the parent page if the page url does not end with a '/'

If a path segment does not have a landing page then should not contain a `/` pr '' key but can still contain settings which apply sub pages of that segment.

```yaml
pages:
    /:                              Welcome To the Site
        onready:                    scripts/onready.js
    /about:
        onready:                    scripts/onready.about-children.js
        timeout:                    40
        /:
            title:                  About Us
            onready:                scripts/onready.about-root..js
        /contact:                   About: Contact Us
        /history:                   About: Organization History
```
