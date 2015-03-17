Content Generator
=================

API and CLI command to pull content from Wikipedia and Pexels.

### Installation

Download & activate the plugin.
Use the CLI command through WP CLI, [see instructions for WP CLI here](wp-cli.org/).

### CLI command

Using a category from Wikipedia and a search term for Pexels.com, you can created any number of posts, pages, or CPTs.

Example:

    wp demo populate --from=Breads --post=10 --with-images=all

This grabs 10 articles in the [Breads](https://en.wikipedia.org/wiki/Category:Breads) category, and creates 10 posts, all with images from [a pexels search for "breads"](http://www.pexels.com/search/breads).

You can specify other post types using the post type name as the flag: `--page=10 --my_post_type=15` etc.

If you want images using a different search term, specify with `--images-from=Fish`.

### API

The plugin adds an endpoint `generator` expecting `POST`'d parameters matching the CLI command, such as

    from='Breads'
    post=10
    page=15
    with-images=some

### `with-images`

This flag's a bit of a trick — you specify how many posts you want to have images, out of "all, most, some, few, none". This translates to a percentage chance (100%, 75%, 50%, 25%, 0%) that an image will be applied. This isn't exact by design, we want randomness in the generated content.
