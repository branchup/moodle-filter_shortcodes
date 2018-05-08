Filter Shortcodes ![GitHub tag](https://img.shields.io/github/tag/branchup/moodle-filter_shortcodes.svg) ![Travis branch](https://img.shields.io/travis/branchup/moodle-filter_shortcodes/master.svg)
=================

Enables users to inject content using shortcodes. The shortcodes are provided by Moodle plugins.

- [Why this plugin?](#why-this-plugin)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [Built-in shortcodes](#built-in-shortcodes)
- [Compatible plugins](#compatible-plugins)
- [End-user documentation](#end-user-documentation)
- [How-to for developers](#how-to-for-developers)
  - [Create a shortcode definition](#create-a-shortcode-definition)
  - [Create the handling method](#create-the-handling-method)
- [Developer documentation](#developer-documentation)
  - [Shortcode attributes](#shortcode-attributes)
  - [Shortcode definition](#shortcode-definition)
  - [Callback arguments](#callback-arguments)
- [Limitations](#limitations)
  - [Backup and restore](#backup-and-restore)
  - [Name conflicts](#name-conflicts)
  - [Nested shortcodes](#nested-shortcodes)
  - [Unrestricted usage](#unrestricted-usage)
- [Ideas](#ideas)

Why this plugin?
----------------

For two reasons:

1. Creating a consistent way for content creators to use shortcodes _à la Wordpress_.
2. Providing an API for developers to build upon without having to create a separate plugin and re-invent the wheel.

By having a standard way to create shortcodes, documentation can be generated automatically for the end-users. End-users only need to be taught once and do not need to know about the intricacies of every single _shortcode-like_ plugin. Administrators also do not need to install a matching _filter_ plugin for each plugin with content-based logic.

And for developers! For example, [Level up!](https://moodle.org/plugins/block_xp) may want to offer teachers the ability to include their student's level and badge in a _page_. The plugin [Stash](https://moodle.org/plugins/block_stash) offers teachers the ability to hide items throughout the course. Not to mention themes who could offer various handy tools, from creating a contact form to formatting content in a theme-specific way, etc...

Neither of these scenarios are achievable without a _filter_ plugin, and that's why we created this plugin, to be the only filter plugin needed for all other plugins to build upon.

Requirements
------------

Moodle 3.1 or greater.

Installation
------------

1. Extract the content of this repository in `filter/shortcodes`
2. Navigate to Site administration > Notifications
3. Follow the prompt to upgrade your Moodle site

Usage
-----

A shortcode is constituted of a word between square brackets. There are two types of shortcodes: the ones that wrap content, and the ones that do not. Those that do wrap content MUST have a closing tag. Here is an example using `[useremail`] which prints the current user's email, and `[toupper]` which wraps content and makes it uppercase.

```
Your registered email address is: [useremail].

[toupper]This text will be uppercased[/toupper].
```

You can also nest the shortcodes, let's make the user's email address uppercase.

```
Your registered email address is: [toupper][useremail][/toupper].
```

Some shortcodes support arguments. Those are declared in the same manner as HTML attributes. Here is an example of a shortcode that would add a collapsible section with a height of 100, and which would be collapsed by default:

```
[section height="100" collapsed]
```

Attribute values do not require to be wrapped between double quotes, but it is recommended. When the attribute does not have a value, it is considered to be `true`. Single quotes cannot be used in lieu of double quotes.

Built-in shortcodes
-------------------

Here are some shortcodes provided by this plugin:

### firstname

Displays the current user's first name.

### fullname

Displays the current user's full name.

### off (wraps content)

Disables the processing of the shortcodes present between its opening and closing tag.

```
[off]
    The shortcode [usermail] prints the current user's email.
[/off]
```

Compatible plugins
------------------

Here is a list of plugins supporting shortcodes:

- [Level up!](https://moodle.org/plugins/block_xp)

_Does your plugin support shortcodes? Send a pull request to add it here!_

End-user documentation
----------------------

A list of all the available shortcodes as well as documentation how to use them is available to users at the URL `https://moodle.example.com/filter/shortcodes/index.php`. The latter page is accessible to all logged in users by default, but that can be tailored using the capability `filter/shortcodes:viewlist`.

The page is not automatically added to the navigation to avoid being too intrusive, we rely on administrators to make this link available to the end-users in their own way.

When the permission to view the list is given in another context than the system context (e.g. given to teachers in courses), the URL should include the parameter `?contextid=123`, where `123` is the context to use to check the permissions.

How-to for developers
---------------------

Declaring a shortcode is very simple, let's create a shortcode returning the current user's email, we will name it `useremail`. We will assume that you are working on a plugin named `local_yourplugin`.

### Create a shortcode definition

Shortcode definitions are set in the file `db/shortcodes.php`, within the variable `$shortcodes`. It uses a similar pattern to capabilities, event observers, cache definitions, etc... The keys of the array will be the name of the shortcode, and the values will be an array of properties. Note that shortcode names can only contain letter and numbers.

There is only one mandatory property to a shortcode definition: `callback`. The callback is a [callable](http://php.net/manual/en/language.types.callable.php) pointing to the autoloaded class method which will handle your shortcode. Ours will be `local_yourplugin\shortcodes::useremail`, which translates to the method `useremail` in the class located at `local/yourplugin/classes/shortcodes.php`.

```php
<?php
defined('MOODLE_INTERNAL') || die();

$shortcodes = [
    'useremail' => [
        'callback' => 'local_yourplugin\shortcodes::usermail'
    ]
];
```


### Create the handling method

Whenever the shortcode is found in content, your callback will be called. Let's create the class and method we defined previously, and return the current user's email from there.

```php
namespace local_yourplugin;
defined('MOODLE_INTERNAL') || die();

class shortcodes {

    public static function useremail() {
        global $USER;
        return $USER->email;
    }

}
```

That's it, your shortcode is now functional. Note that you will need to increase the version number of your plugin in order to force a cache reset, else the new shortcode will not detected. When you are developing, you can simply purge caches between your attempts.

For simplicity we omitted the arguments passed to the method `useremail`, more information in [Callback arguments](#callback-arguments).

Developer documentation
-----------------------

### Shortcode attributes

Shortcodes support attributes. Those attributes will be passed to the callback method. When values are not attached to an attribute it is assumed _true_, similarly to HTML5 attributes. There are no limitations to the format of the attribute names. Double quotes must be used to wrap spaces and equal signs, and to include a double quote within content, escape it with `\`. Single quotes have no special meaning.

```
[shortcode id=2 uid="1234-5678" disabled "Need \"spaces\"?" "Oh my"=w'or'd!]
```

The above example is parsed as:

```
[
    'id' => '2',
    'uid' => '1234-5678',
    'disabled' => true,
    'Need "spaces"?' => true,
    'Oh my' => "w'or'd!"
]
```

### Shortcode definition

They are defined in `db/shortcodes.php` under the array `$shortcodes`. The keys of the array are shortcode names and their values are an array properties. The shortcode names can only contain lowercased letters and numbers (`[a-z0-9]`). Consider using a common short prefix when your shortcodes can conflict with other plugins. The available properties are:

- `callback (callable)` The autoloaded class method to use.
- `wraps (bool) [Optional]` When the shortcode wraps content, and as such has a closing tag, set this to `true`.
- `description (string) [Optional]` The name of the language string (in your component) describing your shortcode.

When you have defined a `description`, you can also define another language string of the same name followed by `_help`. The latter should contain more information about the shortcode, its attributes and how to use it. You may use the Markdown format in the help string.

```php
// db/shortcodes.php
$shortcodes = [
    'weather' => [
        'callback' => 'myplugin\myclass::weather',
        'wraps' => false,
        'description' => 'shortcodeweather'
    ]
]
```

```php
// lang/en/myplugin.php
$string['shortcodeweather'] = 'Displays the weather forecast.';
$string['shortcodeweather_help'] = '
The following attributes can (or must) be used:

- `city` (required) The name of the city to get the forecast for.
- `fahrenheit` (optional) When set, the temperatures will be in Fahrenheit instead of Celcius.

Example:

    [weather city="Perth"]
    [weather city="New York" fahrenheit]
';
```


### Callback arguments

A total of 5 arguments are passed to your callback method.

```php
public static function mycallback($shortcode, $args, $content, $env, $next);
```

- `$shortcode (string)` Is the name of the shortcode found.
- `$args (array)` An associative array of the shortcode arguments.
- `$content (string|null)` When the shortcode `wraps`: the wrapped content.
- `$env (object)` The filter environment object, amongst other things contains the `context`.
- `$next (Closure)` The function to pass the content through when embedded shortcodes should apply.

Here is a complex example of a callback which handles two types of shortcodes:

```php
public static function mycallback($shortcode, $args, $content, $env, $next) {
    global $USER;

    if (!has_capability('moodle/site:config', $env->context)) {
        return '';
    }

    if ($shortcode == 'toupper') {
        // Process embedded content first, then change to uppercase.
        return strtoupper($next($content));

    } else if ($shortcode == 'usermail') {
        return $USER->email;
    }

    return '';
}
```

Limitations
-----------

### Backup and restore

Shortcodes making use of an object ID in their attributes will likely become invalid upon restore if the resource is missing, or the site is different. Let's take the following example which prints a banner for a course:

    [coursebanner id="123"]

When backing up the course, the content will retain `123` as the ID of reference, but when restored the ID will be different and as such the banner will be that of another course, if it exists where the course was restored. To remedy this, we recommend that developers do not use IDs in their shortcodes but unique identifiers, either self-generated or not. In which case, we could have either of the following:

    [coursebanner shortname="my_course_shortname"]
    [coursebanner uid="AbCdE123"]

### Name conflicts

When two plugins define the same shortcode, only one of them will work. It is advised that developers try to make their shortcode as descriptive as possible in order to avoid such conflicts. We intentionally do not require the shortcodes to include their component's name, to keep it simple and more verbose to the end-user. Some plugins, however, may find it useful to use a small prefix for their shortcodes. It is possible that at a later stage we'll enable conflicts to be resolved either through the admin settings, or by allowing the shortcodes to be specific themselves.

### Nested shortcodes

Shortcodes can be nested, however it is up to the shortcodes to determine whether the content they encapsulate should be processed or not.

```
[code1]
    [code2]
        [code3]
            This works.
        [/code3]
    [/code2]
    [code2]Neighbouring content[/code2]
[/code1]
```

Note that a shortcode cannot wrap another shortcode of the same name. The following will __not__ work as intended:

```
[code1]
    [code1]
        This does not work.
    [/code1]
    [code2]Neighbouring content[/code2]
[/code1]
```

### Unrestricted usage

Due to the design of Moodle filters, any user can submit content including any shortcode. Because the shortcode is applied when someone views the content, we cannot restrict the usage of the shortcodes at the source. For example, a student could include as many shortcodes as they want in a forum post, and see what those get transformed into. If they found out about a _secret_ shortcode, they could gain access to information they should not have access to. So, when it is desired for the content displayed by a shortcode to only be available to certain group of users, developers have two options:

__a) Using capabilities__

In their shortcode callback, developers will ensure that the current user has the permissions to view the content. If not, they can return an empty string in order to completely hide the presence of the shortcode. If we had a shortcode displaying a summary of all students' grades in the course, the shortcode callback would validate the permissions of the current user to ensure that they can view those.

__B) Using a secret__

A more advanced usage of shortcodes is Easter egg hunting. Imagine a [plugin](https://moodle.org/plugins/block_stash) that enables a teacher to create eggs and hide them throughout a course. Such eggs could be included in the form of `[egg id="1"]`. However, as students can post any content they want, they could post content containing 1000 shortcodes with the IDs from 1 to 1000. Their chances of discovering hidden eggs by cheating would be very high.

So, firstly the shortcode [should not include an ID](#backup-and-restore), but even if it didn't, to protect our `egg` shortcode from being used by unintended users, we recommend the usage of _secrets_. Secrets could be generated by your plugin, and would be included in the shortcode. When processing the shortcode, the callback would ensure that the secret is valid prior to processing the code, therefore validating that an authorised person included the shortcode in the content. Example:

```
[egg id="1" secret="AbCdEf123"]
```

Ideas
-----

- Support for filtering shortcodes that require a logged in user.
- Support for shortcodes to declare the context they are available in. Example, if a course is needed, the shortcode does not apply elsewhere.
- Support for shortcodes to declare whether the current user can use the code, for display purposes only.
- Provide a helper to help generating short unique identifiers.
- Use a DI container to allow 3rd party devs to manually render some shortcodes, as such that they will be agnostic of current and future implementation of registry and processor.

Provided by
-----------

[![Branch Up](https://branchup.tech/branch-up-logo-x30.svg)](https://branchup.tech)

License
-------

Licensed under the [GNU GPL License](http://www.gnu.org/copyleft/gpl.html).
