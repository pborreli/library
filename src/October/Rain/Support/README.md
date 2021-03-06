## Rain Support

The October Rain Support contains common classes relevant to supporting the other October Rain libraries. It adds the following features:

### A true Singleton trait

A *true singleton* is a class that can ever only have a single instance, no matter what. Use it in your classes like this:

```php
class MyClass 
{
    use \October\Rain\Support\Traits\Singleton;
}

$class = MyClass::instance();
```

### Global helpers

**post()**

Similar to `Input::get()` this returns an input parameter or the default value. However it supports HTML Array names. Booleans are also converted from strings.
```php
$value = post('value', 'not found');
$name = post('contact[name]');
$city = post('contact[location][city]');
```

### String helper extensions

These additional helpers are available in the `Str` class.

**evalHtmlArray**

Converts a HTML array string to a PHP array. Empty values are removed.

```php
// Converts to PHP array ['user', 'location', 'city']
$array = Str::evalHtmlArray('user[location][city]');
```

**stripHtml**

Removes HTML from a string.
```php
// Outputs: Fatal Error! Oh noes!
echo Str::stripHtml('<b>Fatal Error!</b> Oh noes!');
```

### FacadeLoader

Allows "hot-swappable" facades, since `class_alias` cannot be changed dynamically. Facades loaded here can be changed on the fly.

```php
$facade = FacadeLoader::instance();
$facade->facade('Str', 'October\Rain\Support\Facades\Str');
$facade->facade('File', 'October\Rain\Support\Facades\File');
[...]

$str = new Str;
echo get_class($str); // Returns October\Rain\Support\Facades\Str

$facade->facade('Str', 'Some\Other\Facade\Str');
echo get_class($str); // Returns Some\Other\Facade\Str
```
