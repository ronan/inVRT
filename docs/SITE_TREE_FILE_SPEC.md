# Site Tree File format (yaml)

The site tree is a file that describes the site as a tree based on url paths:

It is designed to allow a list of site paths to be stored in a compact readble manner but also
optionally allow metadata for the page (such as page title and test settings) to be stored.

You can also apply test settings to entire branches of the tree as well as to a single page.

## Basic Structure

A site with the following urls:

  - /
  - /about
  - /about/contact
  - /about/history
  - /accounts/aabraham
  - /accounts/bbarnett
  - /past-events
  - /past-events/2021/
  - /past-events/2021/about.html
  - /past-events/2021/content/sessions.html
  - /past-events/2021/venue/eat.html
  - /past-events/2021/venue/travel.html


Would become:

```yaml
/:
  about:
    contact:
    history:
  accounts/*:
    aabraham:
    bbarnet:
  past-events:
    2021/:
      about.html:
      content/sessions.html:
      venue/*:
        eat.html:
        venue/travel.html:
```

Which can be further modified to include more information about each page as discovered during the site crawl:

Meta-data fields are preceded by a `\` to avoid conflicts with page paths.

If any of the urls on the site to be tested contain path segments that start with a backslash, rethink your life choices.

```yaml
/:
  \title:                                   Home Page
  about:
    \title:                                 About Us
    contact:
      \error:                               404 Not found
    history:
      \title:                               About the Organization
  accounts/:
    aabraham:                               Aziz Abraham
    bbarnet:                                Barb Barnet
  past-events:
    2021/:
      about.html:                           About the 2021 Conference
      content/sessions.html:                This Year's Sessions
      venue/*:
        eat.html:                           Where to Eat
        travel.html:                        Getting Here
```

## Shorthand Notation

If a page in the tree does not have any metadata or children it can be represented by an empty object:

```yaml
/:
  about:
  conact:
```

If the page has only a title, it can be represented by a single scalar string:

```yaml
/:
  about:         About Us
  conact:        Contact Us
```

## Applying Settings to the Tree

Test settings can be customized per page or per branch by extending the yaml structure by adding a section called `\settings`

```yaml
/:
  \title':                                   Home Page
  \settings:
    on_load':                                home.onload.js
    timeout':                                60
```

## Flat Branches

If a path has multiple path segments but there are no intermediary landing pages, the whole path can be represented in the key:

```yaml
/:
  about:                     About Us
  accounts/aabraham:          Aziz Abraham
  accounts/bbarnet:           Barb Barnet
```

## Virtual Branches

If you want to apply settings to a common set of flat branches, you can use a virtual branch denoted by ending with a `*`

```yaml
/:
  about:                    About Us
  accounts/*:
    \settings:
      hide_pii:             .username,.email
    aabraham:               Aziz Abraham
    bbarnet:                Barb Barnet
```

## Leaf vs Non-Leaf

In the above example: `/about` is itself a testable page and also contains children while `/accounts` has children but is not itself a testable page.

For `/about` we need to be able to specify settings for the page itself as well as settings that apply to all it's children.

Using a virtual branch with a child `/` path:

```yaml
/:
  about/:
    \title:                              About Us
    \settings:
        on_load:                        about-root.onload.js
  about/*:
    \settings:
        prefix_title:                   'About: '
        on_load:                        about-children.onload.js
        timeout:                        40
    /:
        \title:                         Landing Page
        \settings:
            on_load:                    about.onload.js
    contact:                            Contact Us
    history:                            Organization History
```
