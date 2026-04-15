# Site Tree File format (yaml)

The site tree is a file that describes the site as a tree based on url paths:

It is designed to allow a list of site paths to be stored in a compact readble manner but also
optionally allow metadata for the page (such as page title and test settings) to be stored.

You can also apply test settings to entire branches of the tree as well as to a single page.

## Basic Structure

A site with the following urls:

  - /
  - /about                                      # about has a landing page and sub pages but no trailing slash
  - /about/contact
  - /about/history
  - /accounts/aabraham
  - /accounts/bbarnett
  - /past-events
  - /past-events/2021/                          # this page has sub pages and a landing page with a trailing slash.
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
/.:
/about:
    /contact:
    /history:
/accounts:
    /aabraham:
    /bbarnet:
/past-events:
    /2021/:
        /.:
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

Meta-data fields are contained in angle brackets `<>` to avoid conflicts with page paths.

```yaml
/.:
    <title>:                    Home Page
/about:
    <title>:                    About Us
/contact:
    <error>:                    404 Not found
/history:
    <title>:                    About the Organization
/accounts:
    /aabraham:                  Aziz Abraham
    /bbarnet:                   Barb Barnet
/past-events:
    /2021/:
        /.:                     Welcome to The 2021 Event
        /about.html:            About the 2021 Conference
        /content/sessions.html: This Year's Sessions
        /venue/:
            /eat.html:          Where to Eat
            /travel.html:       Getting Here
```

## Shorthand Notation

If a page in the tree does not have any metadata or children it can be represented by an empty object:

```yaml
/.:
/about:
/conact:
```

If the page has only a title, it can be represented by a single scalar string:

```yaml
/:
    /about:                       About Us
    /conact:                      Contact Us
```

## Applying Settings to the Tree

Test settings can be customized per page or per branch by extending the yaml structure by adding a section called `<settings>`

```yaml
/:
<settings>:
    on_load':                   home.onload.js
    timeout':                   60

```

## Flat Branches

If a path has multiple path segments but there are no intermediary landing pages, the whole path can be represented in the key:

```yaml
/about:                         About Us
/accounts/aabraham:             Aziz Abraham
/accounts/bbarnet:              Barb Barnet
```

## The /. Key

In the above example: `/about` is itself a testable page and also contains children while `/accounts` has children but is not itself a testable page.

For `/about` we need to be able to specify settings for the page itself as well as settings that apply to all it's children.

The key `/.` represents the page that is served at the address represented by the parent object's key. If a path segment does not have a landing page then should not contain a `./` key but can still contain settings which apply sub pages of that segment.

```yaml
/.:                             Welcome To the Site
/about:
    <settings>:
        prefix_title:           'About: '
        on_load:                about-children.onload.js
        timeout:                40
    /.:
      <title>:                  About Us
      <settings>:
        on_load:                about-root.onload.js
    /contact:                   About: Contact Us
    /history:                   About: Organization History
```
