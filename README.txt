
Public Download Count (pubdlcnt) 2.x
    - file download count module for public file system

(C) Copyright 2009 Hideki Ito <hide@pixture.com> Pixture Inc.

(C) Copyright 2017 Corey Halpin <chalpin@scout.wisc.edu>
    Internet Scout Resarch Group.

See LICENSE.txt for licensing terms.


INTRODUCTION
============

Public Download Count is a module that keeps track of file download
count.  This module differs from 'Download Count' in that it is
designed to work with Drupal's public file system.  This module
differs from 'File download count' in that it can track downloads from
external sites (e.g., S3 buckets).

This module converts file URL in the any valid HTML anchor tags on the
fly and use the external PHP program included in this module directory
to keep track of file downloads. The target files are not necessary to
be located under Drupal's installation directory but anywhere
including external servers too.

If you want to protect your files in more secure way, or if you do not
have any strong reasons that you need to use the public file system, I
recommend that you use the Drupal's private file system and the
Download Count module which is designed to work with private file
system. This module is intended for the following people:

1. Those who needs to use the public file system for some reasons, and
   yet want to keep track of file download count.

2. Those who want to keep track of download count of the files on
   different servers.

3. Those who use private file system and still want to keep track of
   download count of direct links to some files.


The goal of this module is to add simple file download count tracking
capability just like using CGI file download counter program with
ease. Therefore, controlling file download itself (access restrictions
by user role, or limit the number of downloads by the same user, etc)
is something that this module would not be meant to do.

This module does not modify the node data when it's stored to the
database. Instead, it converts node data on the fly right before it is
displayed. Therefore, you can enabling/disabling this module at any
time without hurting the node data.

This module works with anchor tags that satisfies the following
criteria:

 1. The anchor tag must have a URL with a file name at its end. It
     does not work with the anchor tags that end with directory name
     or slash (/) character.

 2. The anchor tag must not have any query string. For example,
     following anchor tag is ignored by this module. <a
     href="http://myserver.com/path/program.php?param1=XXXXX&param2=YYYYY>

 3. The anchor tag must not have 'rel=lightbox...' attribute which
     means that it is the anchor to an image to be handled by the
     lightbox module to avoid the conflict problem with the lightbox
     module. (thickbox and shadowbox too)

 4. The anchor tag must have a file name with a valid file name
     extension. Valid file name extesions can be customized at the
     module's configuration page.

 5. The anchor tag must not have a file path to the private file
     system (which includes /system/files in its path).

 6. The anchor tag must be in nodes of supported node types, not in
    comments. This module does not handle anchor tags in comments.


How it works
------------

This module keep track of file downloading operation and records the
download count for each file (by file name) on daily basis.

You can either enable/disable the 'save history record' option at the
module's configuration page. If this option is enabled, you can see
the stastics of download count for each file by daily, monthly or even
yearly later.  If this option is disabled, it only keeps track of the
accumulated download counter and the last download date.

When you install and enable this module, this module adds two pages,
one under site configuration and the other one under report section of
administer site.

The report page of this module shows the summary of download count for
all the files and you can see the summary of each file in a separate
page. You can also download the counter data as a tab separated text
file using the Export feature of this module.

The configration page of this module let you specify optional settings
including the extension of the file name you want to keep track of and
the node types supported.

NOTE: File download counter is incremented even if the downloading is
aborted/cancelled in the middle. However, the counter would not be
increased if the target file does not exist.


Customizing counter display
---------------------------

Using phptemplate function, you can customize the download counter
display in nodes and blocks. To do this create your own
phptemplate_pubdlcnt_counter() function in your theme's template.php
file. You can find the sample in the sample-template.php file in this
directory. The header and comment of the file explains more details.


REQUIREMENTS
============

No special requeriments.


RECOMMENDED MODULES
===================

* Views (https://www.drupal.org/project/views)
  On the Views page, you will find that "Public download count" group
  is added for filter, field, and relationships. For field and filter,
  file name, file URL, download count, last download time are
  available. To keep track the download count of the file listed in
  Views pages/blocks, this module uses theme template file
  'pubdlcnt-views-view-field.tpl.php' in the views subdirectory. This
  template file makes it possible this module to convert anchor tags
  in any Views fields.


INSTALLATION
============

 * Install as you would normally install a contributed Drupal module. Visit:
   https://drupal.org/documentation/install/modules-themes/modules-7
   for further information.

CONFIGURATION
=============

* Configure user permissions in Administration > People > Permissions:

 - Administer Public Download Count module
   Users with this permisson can view global statistics on all
   download files and can reset download counts for specific files to
   zero.

 - View total download counts in nodes
   Users with this permisson can see the total number of times a file
   has been downloaded displayed next to the link to the file in
   content nodes.

 - View total download counts in block
   Users with this permisson can see the number of times each file has
   been downloaded when viewing the 'Top Downloads' block.

* Customize module settings via Modules, then the configuration gear
  at right of Public Download Count

  - Configure the list of file extensions for which downloads should
    be tracked

  - Configure the list of node types on which downloads will be
    tracked. If none are selected (the default), downloads will be
    tracked on all nodes

  - If desired, enable 'Skip duplicate download' to count one download
    per IP per day

  - If desired, disable 'Save download history records' to reduce the
    amount of data stored for each file. This mode will track only the
    total download count for each file and the time of the most recent
    download.


DESIGN LIMITATIONS AND KNOWN ISSUES
===================================

* As explained earlier, this module does not work well with the anchor
  tag of image files handled by the lightbox module. Therefore, this
  module does not count the image files handled by lightbox even when
  .jpg, .png, .gif extensions are included to the valid
  extensions. (thickbox and shadowbox too). I think this would be OK
  since these anchor is used for displaying images instead of
  downloading image file.

* This module uses file name as a base of the counter. Therefore, if
  files with the same name exist in several places, it treats them as
  a single file counter. In such a case, node id (nid) and URL of the
  file stored in a database table is updated based on the ones of the
  file downloaded the last time.

(End)
