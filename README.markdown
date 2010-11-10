# WordPress Posts (wp_posts)

This library makes it possible to use WordPress as a simple and usable admin tool without having to use the entire front-end and theme code.

To use it, simply install WordPress somewhere and add posts using the WordPress admin tool. Then point this library at the same database that WordPress uses, and it will be able to dynamically pull the posts and other information.

### Supported Features

wp_posts currently supports pulling posts and categories from the latest verison of WordPress (3.0.1) directly from the database for use in any situation you can imagine. You can integrate with standard or multisite WordPress installations.

### Unsupported Features

* pages
* links / blogroll
* widgets.

At the moment, the unsupported features may never be supported. It depends largely on whether a need for them is found. If you are interesting in adding these features, please let me know so that we can collaborate.


### Is this for you?
The main idea of WordPress Off Road is to simply and quickly use WordPress as an admin tool without the overhead of a developing themes and so on. We want to keep things simple. If you need to use all of WordPress' features in your site and need nothing else, then you should probably use WordPress' own code. If you want something simple and need to integrate posts easily into another website, then this library might be a good choice for you.
