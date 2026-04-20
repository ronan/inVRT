# Plans for the rebuilt testing report

## Brief:

Build a static html report which displays the results of previously run commands in a pleasant to use way.

Easy to share:
    Simple html file that can be shared or hosted anywhere
    Relative path images 
        or Links to a hosted service (https://invrt.report)
        or Base64 embedded images in the html file

## Components:

### Site Info

- A cropped screengrab of the homepage
- The site base url
- Site title
- Available environments, profiles, devices
  

### Page Inventory

A list of every discovered page on the site and it's current state.

- Page title
- Page path
- Baseline thumbnail if available
- Info on last capture
- Which profiles have access

### Page Detail Screen

By clicking on a page or typing enter you enter the page detail screen.

- Side by side comparison of reference and last failed test
  - Scroll-synced for comparison
  - Overlay mode
  - Difference mode
- Switch between devices to see baseline and any changes
- Page info and crawlng details
- Instructions on how to rerun the test for this page
- Instructions on how to approve changes

### Tree View

An overview of the site that lets you see the structure of your site and navigate it quickly.

- Path segment
- "dirty" marker

### Resources

https://daisyui.com/components/
https://htmx.org

