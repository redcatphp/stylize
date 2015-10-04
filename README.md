 Stylize
========

 Stylize is a CSS Pre-processor using Scss syntax from [SASS](http://sass-lang.com) ( version 3.2 ) ported to PHP with additional features.  
 If you're not familiar with this language, you can consult the basic documentation on [SASS](https://en.wikipedia.org/wiki/Sass_%28stylesheet_language%29). I'll describe here only the PHP API and exclusive features of Stylize. The source code is derived from the excellent [leafo-scssphp](http://leafo.net/scssphp/).

Additional Features to SCSS
---------------------------

- [php imbrication](http://wildsurikat.com/Documentation/Stylize#php-imbrication)
- [hybride PHP Mixin](http://wildsurikat.com/Documentation/Stylize#php-mixin)
- [mixin autoload (include)](http://wildsurikat.com/Documentation/Stylize#autoload-mixin)
- [extend autoload](http://wildsurikat.com/Documentation/Stylize#autoload-extend)
- [font autoload](http://wildsurikat.com/Documentation/Stylize#autoload-font)

Basic Usage
-----------

### Compiler

 
```php
$scss = new \\Wild\\Stylize\\Compiler();  
$scss->setImportPaths(['css']);  
$scss->addImportPath('surikat/css');  
$scss->compile(file_get_contents('css/style.scss'));  
            
```


### Server

 The server will handle cache and rebuild it only if the files has changed and also deal in HTTP via Etag and Last-Modified. It also include by default, if they are present, "*_config.scss*" and "*_var.scss*". It will use a cache directory by default which is "*.tmp/stylish/*" from current working directory and which need to be writeable (chmod 0777). 
```php
$server = new \\Wild\\Stylize\\Server();  
$directories = ['css','surikat/css'];  
$server->serveFrom('style.scss',$directories);  
            
```


PHP Support
-----------

 The php will be executed before SCSS syntax parser.  
 By dint of [tokenizer](http://php.net/manual/en/book.tokenizer.php), the php support allow you tu use short php syntax even if *short_open_tag* is not enabled in [*php.ini*](http://php.net/manual/en/ini.core.php).

### Imbrication

 
```scss
$img-path: '<?=$img_path?>' !default;  
  
<?if($bool){?>  
    // here is your scss code  
<?}?>  
  
<?foreach($elements as $key=>$val):?>  
    // here is your scss code  
<?endforeach;?>  
            
```


### Hybride PHP Mixin

 The hybride PHP mixins allow you to get your parameters passed to *include* as php variables in *mixin* declaration and using a different syntax for *include* parameters.  
 The syntax of hybride php mixins parameters is simple: the separator is the comma "*,*" and no quotes are required, all parameters will be automaticaly typed.  
 The difference in declaration is that you have to use a "*@?*" instead of "*@*" and same for *include*: "*@?mixin *" instead of "*@mixin *" and "*@?include *" instead of "*@include *".  
 Let's take an example of declaration (the grid from [Surikat SCSS Toolbox](http://wildsurikat.com/Documentation/CSS)): 
```scss
@import "include/grid.reset-star";  
@?mixin grid{<?  
    $selector = is_string($e=current($args))&&!is_numeric(str_replace(array('-',',','.'),'',$e))?array_shift($args):false;  
    $mw = is_string($e=end($args))&&substr($e,-2)=='px'?array_pop($args):false;  
    $tt = 0;  
    foreach($args as $i=>$w){  
        ?>  
            <?if($mw):?>  
                @media(min-width:<?=$mw?>){  
            <?endif;?>  
                <?if($selector):?>  
                    ><?=$selector?>{  
                <?else:?>  
                    >*:nth-child(<?=$i+1?>){  
                <?endif;?>  
                        <?if(!$mw):?>  
                            position: relative;  
                            display:block;  
                            float:left;  
                            min-height: 1px;  
                            -webkit-box-sizing: border-box;  
                            -moz-box-sizing: border-box;  
                            box-sizing: border-box;  
                        <?endif;?>  
                        <?if($tt>=100):?>  
                            <?$tt = 0;?>  
                            clear:left;  
                        <?else:?>  
                            clear:none;  
                        <?endif;?>  
                        <?  
                            if(is_string($w)){  
                                @list($margin_left,$w,$margin_right) = explode(',',str_replace('-',',',$w));  
                                if($margin_left){  
                                    ?>margin-left:<?=$margin_left?>%;<?  
                                    $tt += $margin_left;  
                                }  
                                if($margin_right){  
                                    ?>margin-right:<?=$margin_right?>%;<?  
                                    $tt += $margin_right;  
                                }  
                            }  
                            ?>width: <?=$w?$w.'%':'auto'?>;<?  
                        ?>  
                    }  
            <?if($mw):?>  
                }  
            <?endif;?>  
        <?  
        $tt += $w;  
    }  
?>}?@  
            
```
   
And then, a usage:  
 
```scss
body>header>h1>a{  
    @?include grid(*,    2-96-2);  
    @?include grid(100        ,1-48-1,    1-48-1,    480px);  
    @?include grid(1-51-1    ,1-21-1,    1-21-1,    768px);  
}  
            
```


Autoload Support
----------------

### Mixin

 If the mixin to include isn't allready defined when used, the autoload support will look for presence of file corresponding to name of mixin in *import paths* followed by "*include*" and then "*.scss*" extension: $import-path/include/$name-of-mixin.scss. If they exists it will import them. 
```scss
@include clearfix();  
@?include icon(css3);  
            
```
 This code will trigger autoload to look for include/clearfix.sccs and include/icon.scss in the *import paths* and import them.

### Extend

 If the class to extend isn't allready defined when used, the autoload support will look for presence of file corresponding to name of class in *import paths* followed by "*extend*" and then "*.scss*" extension: $import-path/extend/$name-of-class.scss. If they exists it will import them. 
```scss
body>footer>div:first-child{  
    @extend %surikat-powered;  
}  
            
```
 This code will trigger autoload to look for extend/surikat-powered.scss in the *import paths* and import it.

### Font

 If the font-face declaration corresponding to font-familly which is used isn't allready defined when used, the autoload support will look for presence of file corresponding to name of font-family (lowercase and with spaces replaced by hyphen *-*) in *import paths* followed by "*font*" and then "*.scss*" extension: $import-path/font/$name-of-font.scss. If they exists it will import them. That doesn't work with variable font-name. 
```scss
header{  
    font-family: Indie Flower;  
}  
body{  
    font: bold 10px Rock Salt;  
}  
            
```
 This code will trigger autoload to look for font/indie-flower.scss and font/rock-salt.scss in the *import paths* and import it.
