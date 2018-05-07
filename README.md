Polylang Fix Relationships 
==========================

**This Plugin is discontinued.**  
You might like to take a look at [Polylang Sync](https://github.com/mcguffin/polylang-sync) here on GitHub, which addresses a similar issue.

---

Manage post relationships like attachments, post parents and ACF Relational fields which are not 
covered by [Polylang](http://polylang.wordpress.com) plugin.

Compatibility
-------------
Tested With WP 4.4.2 - 4.5.2, Polylang 1.8.4 - 1.9, ACF pro 5.x


Features:
---------
 - Adds a clone post feature
 - Clones post attachments to corresponding languages
 - Changes post thumbnail and ACF Relations (like post object, image, ...) to their corresponding translations

Plugin API
----------

There are two filters allowing a developer to disable one of the two two features:

    // disables the Fix Relationships row action
    add_filter( 'polylang_relationships_enable_fix', '__return_false' );

    // disables the Create4 Translations row action
    add_filter( 'polylang_relationships_enable_clone', '__return_false' );

