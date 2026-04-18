# File based process flow

## Summary: 

The progress of a multi-step process is tracked using the presence and contents of formatted text files.

## Current state:

The app has multiple steps that it can run separately or in sequence to scan, analyze and test websites.

The steps, in order are:

invrt init <url>
invrt check
invrt crawl
invrt reference
invrt test
invrt approve

Each step creates a file which the next step can check to see if the previous one has run. The app runs previous required steps as needed whenever you run a later step in the chain. 

From an empty directory you could run:

INVRT_URL=http://localhost invrt approve

And the app would run each step in sequence. Steps are not rerun by default. If you want to rerun a previous step you can run it directly.

The steps produce the followinfg files

| ***cmd*** | **file** | **contents** |
| -- | -- | -- |
| init | config.yaml | default site settings |
| check | check.yaml | scraped site info |
| crawl | crawled_urls.txt | crawled site paths |
| reference | reference_results.txt | the logs from the run |
| test | test_results.txt | the logs from the run |

## Advantages

- Simple. 
- Static.
- Can be stored in repository.
- Files are useful to humans.
- A single point of status and storage. 
- Minimal external requirements (no db, no api). 
- Work well with existing tools.

## Disadvantages

- Not great for concurrent access

## Improvements

### Structure and style

The file name styles and format are inconsistent and are spread too deeply in the data directory structure. Proposed new structure:

```
.invrt/
    Config.yml
    Plan.yml
    Backstop.ts
    Report.html
    data/
        anonymous/
            CrawledPaths.txt
            Reference.log
            local/
                logs/
                    Crawl.log
                    Test.log
        bitmaps/
            approved/
                anonymous-desktop-qwerfas.png
                ...
            test/
                local/
                prod/
    scripts/
```

This removes the differences between environments in crawl directory. The site is assumed to have the same paths in every environment.

We also assume that the reference happens with the base url or if it is done on a different environment we still want to overwrite the log and bitmaps.

### More functional files

By turning more files into usable config and script files we can allow the tool to be used as a building block for more complex tests. Ideally we would allow these functional files to be edited and still rebuilt with the user changes reapplied.

Some functional files we could generate:

### backstop.json

We are already creating a static backstop file. We could do this at the end of the crawl step to allow users to edit it before the reference/capture run.

### playwrite.test.ts

If we bypass backstop we could generate most of what it does with a generated playwrite test file that can be run directly in a playwrite container (or in the cloud)

### report.html

This should be a self contained report that can be uploaded (with the bitmaps) to be viewed. It is alreadsy but this could be better. Improvements:

- The whole test in a single page, ability to compare environments, profiles etc
- Embedded images to make a single file interactive report

