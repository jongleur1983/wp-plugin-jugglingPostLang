# wp-plugin-jugglingPostLang

JugglingPostLang allows to define a language per post.
The main concern that lead to this plugin was to allow different posts of the same wordpress blog to be written in different languages.
You might say, just do it, but I wanted it to stay accessible.

For search engines and other web crawlers as well as for accesibility of the website itself it's important to know the language of a text.
If that is known a screenreader can adapt the pronunciation of words to the particular languages rules.

Wordpress at it's core supports one global language for the whole blog, but not for an individual article.

There are more plugins to support something like that. Most of them have much more features I don't need, so I wrote my own.

## Installation

To install this plugin just copy the jugglingPostLang directory to your wordpress plugins directory and activate it in the plugins list.

## Configuration

To add more locales use the "Languages" taxonomy that can be manipulated in the posts submenu.
Currently the name of a value here directly matches to the lang="..." attribute injected on the frontend.

## Usage

When writing or editing a post the plugin is responsible for an additional meta-box titled "Language". 
The select list contains all available languages according to the Configuration (see above).
Default value is " - undefined - ", which can't be set by the user, but stays set as long as nothing else is chosen.

Once a post has a value here, title and content of that post will be wrapped in a span that defines it's language.

This should work on all themes that use the_content() for the posts content and the_title() to display the title.

## Known issues

+ Any theme that somehow masks the content or title by replacing special chars with their entities or by stripping html code will fail.
+ If content or title are used outside the main loop of wordpress, the language is not set there.
+ If a stylesheet is defined for arbitrary span elements, the styling may break.