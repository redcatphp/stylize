<?php
namespace RedCat\Stylize;
use Leafo\ScssPhp\Formatter\OutputBlock;
use Leafo\ScssPhp\Type;
use Leafo\ScssPhp\Node\Number;
class Compiler extends \Leafo\ScssPhp\Compiler{
    const Stylize_VERSION = 'v2';
    const Scss_VERSION = 'v0.6.3';
	
	function parserFactory($path){
        $parser = new Parser($path, count($this->sourceNames), $this->encoding, $this);

        $this->sourceNames[] = $path;
        $this->addParsedFile($path);

        return $parser;
    }
	
	public $dev = true;
	function setDev($mode=true){
		$this->dev = $mode;
	}
	function phpScssSupport($code){
		$code = $this->mixinSphpSupport($code);
		$code = $this->autoloadSphpSupport($code);
		$code = $this->shortOpentagSupport($code);
		$code = $this->autoloadScssSupport($code);
		$code = $this->fontSupport($code);
		$code = $this->evalFree($code);
		return $code;
	}
	protected function mixinSphpSupport($code){
		preg_match_all('/@\\?mixin\\s+([a-zA-Z0-9-]+)\\{(.*?)\\}\\?@/s',$code,$matches);
		if(!empty($matches)&&!empty($matches[0])){
			$pos = 0;
			foreach(array_keys($matches[0]) as $i){
				$fname = str_replace('-','_',$matches[1][$i]);
				$rep = '<?php if(!function_exists("'.$matches[1][$i].'")){ function scss_'.$fname.'($args){?>'.$matches[2][$i].'<?php }}?>';
				$code = substr($code,0,$pos=strpos($code,$matches[0][$i],$pos)).$rep.substr($code,$pos+strlen($matches[0][$i]));
			}
		}
		return $code;
	}
	protected function shortOpentagSupport($code){ //support of short open tag even if not supported by php.ini
		$r = [
			'<?'=>'<?php ',
			'<?php php'=>'<?php ',
			'<?php ='=>'<?=',
		];
		$code = str_replace(array_keys($r),array_values($r),$code);
		$tokens = token_get_all($code);
		$code = '';
		$opec = false;
		foreach($tokens as $token){ 
			if(is_array($token)){
				switch($token[0]){
					case T_OPEN_TAG:
						$code .= '<?php ';
					break;
					case T_OPEN_TAG_WITH_ECHO:
						$opec = true;
						$code .= '<?php echo ';
					break;
					case T_CLOSE_TAG:
						if($opec&&substr(trim($code),-1)!=';')
							$code .= ';';
						$code .= '?>';
						$opec = false;
					break;
					default:
						$code .= $token[1];
					break;
				}
			}
			else
				$code .= $token;
		}
		return $code;
	}
	protected function autoloadSphpSupport($code){
		$pos = 0;
		preg_match_all('/\/\/@\\?([^\\r\\n]+)/s',$code,$matches); //strip
		if(!empty($matches)&&!empty($matches[0]))
			foreach(array_keys($matches[0]) as $i)
				$code = substr($code,0,$pos=strpos($code,$matches[0][$i],$pos)).substr($code,$pos+1+strlen($matches[0][$i]));
		preg_match_all('/@\\?include\\s+([a-zA-Z0-9-]+)\\((.*?)\\);/s',$code,$matches);
		$pos = 0;
		if(!empty($matches)&&!empty($matches[0])){
			foreach(array_keys($matches[0]) as $i){
				$fname = str_replace('-','_',$matches[1][$i]);
				$func = 'scss_'.$fname;
				$arg = $matches[2][$i];
				$r = [
					'['=>'(',
					']'=>')',
					'{'=>'(',
					'}'=>')',
					'=>'=>':',
					'='=>':',
					':'=>'=>',
					"\t"=>"",
					"\n"=>"",
					"\r"=>"",
					')('=>'),(',
					'('=>'array(',
				];
				$arg = str_replace(array_keys($r),array_values($r),'('.trim($arg).')');
				preg_match_all('/([a-zA-Z0-9-$*]+)/s',$arg,$am);
				if(isset($am[0])){
					$_pos = 0;
					foreach($am[0] as $y=>$m){
						if(
							$m!='array'
							&&$m!='true'
							&&$m!='false'
							&&!is_numeric($am[1][$y])
							&&strpos($m,'$')===false
						){
							$s = substr($arg,0,$_pos=strpos($arg,$m,$_pos+2));
							$e = substr($arg,$_pos+strlen($m));
							if(($_s=substr($s,-1))!='"'&&$_s!="'")
								$s .= '"';
							if(($_e=substr($e,0,1))!='"'&&$_e!="'")
								$e = '"'.$e;
							$arg = $s.$am[1][$y].$e;
						}
					}
				}
				if(!function_exists($func)&&($path = $this->findImport('include/'.$matches[1][$i])))
					$this->importFile($path,$this->scope);
				if(!function_exists($func))
					$this->throwError('Call to undefined mixin at "@?include '.$fname.'( ..."');
				$arg = "<?$func($arg);?>";
				$code = substr($code,0,$pos=strpos($code,$matches[0][$i],$pos)).$arg.substr($code,$pos+strlen($matches[0][$i]));
			}
		}
		return $code;
	}
	protected function autoloadScssSupport($code){
		$tmpCode = $code;
		preg_match_all('/\/\/([^\\r\\n]+)/s',$tmpCode,$matches); //strip
		if(!empty($matches)&&!empty($matches[0]))
			foreach(array_keys($matches[0]) as $i)
				$tmpCode = substr($tmpCode,0,$pos=strpos($tmpCode,$matches[0][$i])).substr($tmpCode,$pos+1+strlen($matches[0][$i]));
		preg_match_all('~/\*.*?\*/~s',$tmpCode,$matches); //strip
		if(!empty($matches)&&!empty($matches[0]))
			foreach(array_keys($matches[0]) as $i)
				$tmpCode = substr($tmpCode,0,$pos=strpos($tmpCode,$matches[0][$i])).substr($tmpCode,$pos+1+strlen($matches[0][$i]));
		preg_match_all('/@include\\s+([^\\(\\);]+)/s',$tmpCode,$matches);
		if(!empty($matches)&&!empty($matches[0])){
			foreach(array_keys($matches[0]) as $i){
				if(strpos($matches[1][$i],'#{')!==false)
					continue;
				$code = "@import 'include/{$matches[1][$i]}';\r\n$code";
			}
		}
		preg_match_all('/@extend\\s+([^;]+)/s',$tmpCode,$matches);
		if(!empty($matches)&&!empty($matches[0])){
			foreach(array_keys($matches[0]) as $i){
				if(strpos($matches[1][$i],'#{')!==false)
					continue;
				$inc = ltrim(str_replace('%','-',$matches[1][$i]),'-');
				$code = "@import 'extend/$inc';\r\n$code";
			}
		}
		return $code;
	}
	protected function fontSupport($code){
		$pos = 0;
		$tmpCode = $code;
		preg_match_all('/#\\{([^\\}]+)/s',$tmpCode,$matches); //strip
		if(!empty($matches)&&!empty($matches[0]))
			foreach(array_keys($matches[0]) as $i)
				$tmpCode = substr($tmpCode,0,$pos=strpos($tmpCode,$matches[0][$i])).'#var#'.substr($tmpCode,$pos+1+strlen($matches[0][$i]));
		preg_match_all('/\/\/([^\\r\\n]+)/s',$tmpCode,$matches); //strip
		if(!empty($matches)&&!empty($matches[0]))
			foreach(array_keys($matches[0]) as $i)
				$tmpCode = substr($tmpCode,0,$pos=strpos($tmpCode,$matches[0][$i])).substr($tmpCode,$pos+1+strlen($matches[0][$i]));
		preg_match_all('~/\*.*?\*/~s',$tmpCode,$matches); //strip
		if(!empty($matches)&&!empty($matches[0]))
			foreach(array_keys($matches[0]) as $i)
				$tmpCode = substr($tmpCode,0,$pos=strpos($tmpCode,$matches[0][$i])).substr($tmpCode,$pos+1+strlen($matches[0][$i]));
		preg_match_all('/@font-face([^\\}]+)/s',$tmpCode,$matches); //strip
		if(!empty($matches)&&!empty($matches[0]))
			foreach(array_keys($matches[0]) as $i)
				$tmpCode = substr($tmpCode,0,$pos=strpos($tmpCode,$matches[0][$i])).substr($tmpCode,$pos+1+strlen($matches[0][$i]));
		preg_match_all('/font-family(\\s+|):([^\\(\\);]+)/s',$tmpCode,$matches);
		if(!empty($matches)&&!empty($matches[0])){
			$pos = 0;
			foreach(array_keys($matches[0]) as $i){
				if(strpos($matches[2][$i],'$')!==false)
					continue;
				$font = str_replace(' ','-',strtolower(trim(str_replace([':','"',"'"],'',$matches[2][$i]))));
				$x = explode(',',$font);
				foreach($x as $f){
					$this->autoGenerateFont($f);
					$code = "@import 'font/$f';\r\n$code";
				}
				$tmpCode = substr($tmpCode,0,$pos=strpos($tmpCode,$matches[0][$i],$pos)).substr($tmpCode,$pos+1+strlen($matches[0][$i])); //strip
			}
		}
		preg_match_all('/font(\\s+|):([^\\(\\);]+)/s',$tmpCode,$matches);
		if(!empty($matches)&&!empty($matches[0])&&trim($matches[2][0])){
			foreach(array_keys($matches[0]) as $i){
				if(strpos($matches[0][$i],'{')!==false||strpos($matches[0][$i],'$')!==false)
					continue;
				if(strpos($matches[2][$i],'#var#')!==false)
					continue;
				$font = strtolower(trim(str_replace([':','"',"'"],'',$matches[2][$i])));
				$y = [];
				$x = explode(' ',$font);
				foreach($x as $f){
					if(strpos($f,'/')===false&&(!(int)substr($f,0,-2)||(($e=substr($f,-2))!='px'&&$e!='em'))&&(!(int)substr($f,0,-1)||(substr($f,-1)!='%'))&&!in_array($f,['bold','small','normal','bolder','lighter','inherit','initial','unset'])&&(string)(int)$f!==$f)
						$y[] = $f;
				}
				$font = implode('-',$y);
				$x = explode(',',$font);
				foreach($x as $f){
					$this->autoGenerateFont($f);
					$code = "@import 'font/$f';\r\n$code";
				}
			}
		}
		return $code;
	}
	protected function evalFree($__code){
		ob_start();
		$o = &$this;
		$h = set_error_handler(function($errno, $errstr, $errfile, $errline)use($o,$__code){
			if(0===error_reporting())
				return false;
			ob_get_clean();
			$o->throwError(" error in eval php: %s \r\n in code: %s",$errstr,$__code);
		});
		if($this->dev&&strpos($__code,'//:eval_debug'))
			exit(print($__code));
		eval('?>'.$__code);
		$c = ob_get_clean();
		set_error_handler($h);
		return $c;
	}
	
	protected function importFile($path, $out)
    {
        // see if tree is cached
        $realPath = realpath($path);

        if (isset($this->importCache[$realPath])) {
            $this->handleImportLoop($realPath);

            $tree = $this->importCache[$realPath];
        } else {
            $code   = file_get_contents($path);
            $parser = $this->parserFactory($path);
            $tree   = $parser->parse($code);

            $this->importCache[$realPath] = $tree;
            
            //addon by surikat
            $x = explode('/',dirname($path));
			$dotScss = [];
			while(!empty($x)){
				$dir = implode('/',$x);
				array_pop($x);
				if(is_file($dir.'/.scss')){
					$dotScss[] = $dir.'/.scss';
				}
			}
			if(!empty($dotScss)){
				$dotScss = array_reverse($dotScss);
				foreach($dotScss as $dscss){
					$this->importFile($dscss,$out);
				}
			}
			
        }

        $pi = pathinfo($path);
        array_unshift($this->importPaths, $pi['dirname']);
        $this->compileChildrenNoReturn($tree->children, $out);
        array_shift($this->importPaths);
    }
	protected function compileChild($child, OutputBlock $out)
    {
        $this->sourceIndex  = isset($child[Parser::SOURCE_INDEX]) ? $child[Parser::SOURCE_INDEX] : null;
        $this->sourceLine   = isset($child[Parser::SOURCE_LINE]) ? $child[Parser::SOURCE_LINE] : -1;
        $this->sourceColumn = isset($child[Parser::SOURCE_COLUMN]) ? $child[Parser::SOURCE_COLUMN] : -1;

        switch ($child[0]) {
            case Type::T_SCSSPHP_IMPORT_ONCE:
                list(, $rawPath) = $child;

                $rawPath = $this->reduce($rawPath);

                //if (! $this->compileImport($rawPath, $out, true)) {
                if (!$this->compileImport($rawPath, $out, true)&&(pathinfo($this->compileValue($rawPath),PATHINFO_EXTENSION)=='css')) { //surikat
                    $out->lines[] = '@import ' . $this->compileValue($rawPath) . ';';
                }
                break;

            case Type::T_IMPORT:
                list(, $rawPath) = $child;

                $rawPath = $this->reduce($rawPath);

                //if (! $this->compileImport($rawPath, $out)) {
                if (!$this->compileImport($rawPath, $out)&&(pathinfo($this->compileValue($rawPath),PATHINFO_EXTENSION)=='css')) { //surikat
                    $out->lines[] = '@import ' . $this->compileValue($rawPath) . ';';
                }
                break;

            case Type::T_DIRECTIVE:
                $this->compileDirective($child[1]);
                break;

            case Type::T_AT_ROOT:
                $this->compileAtRoot($child[1]);
                break;

            case Type::T_MEDIA:
                $this->compileMedia($child[1]);
                break;

            case Type::T_BLOCK:
                $this->compileBlock($child[1]);
                break;

            case Type::T_CHARSET:
                if (! $this->charsetSeen) {
                    $this->charsetSeen = true;

                    $out->lines[] = '@charset ' . $this->compileValue($child[1]) . ';';
                }
                break;

            case Type::T_ASSIGN:
                list(, $name, $value) = $child;

                if ($name[0] === Type::T_VARIABLE) {
                    $flag = isset($child[3]) ? $child[3] : null;
                    $isDefault = $flag === '!default';
                    $isGlobal = $flag === '!global';

                    if ($isGlobal) {
                        $this->set($name[1], $this->reduce($value), false, $this->rootEnv);
                        break;
                    }

                    $shouldSet = $isDefault &&
                        (($result = $this->get($name[1], false)) === null
                        || $result === self::$null);

                    if (! $isDefault || $shouldSet) {
                        $this->set($name[1], $this->reduce($value));
                    }
                    break;
                }

                $compiledName = $this->compileValue($name);

                // handle shorthand syntax: size / line-height
                if ($compiledName === 'font') {
                    if ($value[0] === Type::T_EXPRESSION && $value[1] === '/') {
                        $value = $this->expToString($value);
                    } elseif ($value[0] === Type::T_LIST) {
                        foreach ($value[2] as &$item) {
                            if ($item[0] === Type::T_EXPRESSION && $item[1] === '/') {
                                $item = $this->expToString($item);
                            }
                        }
                    }
                }

                // if the value reduces to null from something else then
                // the property should be discarded
                if ($value[0] !== Type::T_NULL) {
                    $value = $this->reduce($value);

                    if ($value[0] === Type::T_NULL || $value === self::$nullString) {
                        break;
                    }
                }

                $compiledValue = $this->compileValue($value);

                $out->lines[] = $this->formatter->property(
                    $compiledName,
                    $compiledValue
                );
                break;

            case Type::T_COMMENT:
                if ($out->type === Type::T_ROOT) {
                    $this->compileComment($child);
                    break;
                }

                $out->lines[] = $child[1];
                break;

            case Type::T_MIXIN:
            case Type::T_FUNCTION:
                list(, $block) = $child;

                $this->set(self::$namespaces[$block->type] . $block->name, $block);
                break;

            case Type::T_EXTEND:
                list(, $selectors) = $child;

                foreach ($selectors as $sel) {
                    $results = $this->evalSelectors([$sel]);

                    foreach ($results as $result) {
                        // only use the first one
                        $result = current($result);

                        $this->pushExtends($result, $out->selectors, $child);
                    }
                }
                break;

            case Type::T_IF:
                list(, $if) = $child;

                if ($this->isTruthy($this->reduce($if->cond, true))) {
                    return $this->compileChildren($if->children, $out);
                }

                foreach ($if->cases as $case) {
                    if ($case->type === Type::T_ELSE ||
                        $case->type === Type::T_ELSEIF && $this->isTruthy($this->reduce($case->cond))
                    ) {
                        return $this->compileChildren($case->children, $out);
                    }
                }
                break;

            case Type::T_EACH:
                list(, $each) = $child;

                $list = $this->coerceList($this->reduce($each->list));

                $this->pushEnv();

                foreach ($list[2] as $item) {
                    if (count($each->vars) === 1) {
                        $this->set($each->vars[0], $item, true);
                    } else {
                        list(,, $values) = $this->coerceList($item);

                        foreach ($each->vars as $i => $var) {
                            $this->set($var, isset($values[$i]) ? $values[$i] : self::$null, true);
                        }
                    }

                    $ret = $this->compileChildren($each->children, $out);

                    if ($ret) {
                        if ($ret[0] !== Type::T_CONTROL) {
                            $this->popEnv();

                            return $ret;
                        }

                        if ($ret[1]) {
                            break;
                        }
                    }
                }

                $this->popEnv();
                break;

            case Type::T_WHILE:
                list(, $while) = $child;

                while ($this->isTruthy($this->reduce($while->cond, true))) {
                    $ret = $this->compileChildren($while->children, $out);

                    if ($ret) {
                        if ($ret[0] !== Type::T_CONTROL) {
                            return $ret;
                        }

                        if ($ret[1]) {
                            break;
                        }
                    }
                }
                break;

            case Type::T_FOR:
                list(, $for) = $child;

                $start = $this->reduce($for->start, true);
                $start = $start[1];
                $end = $this->reduce($for->end, true);
                $end = $end[1];
                $d = $start < $end ? 1 : -1;

                while (true) {
                    if ((! $for->until && $start - $d == $end) ||
                        ($for->until && $start == $end)
                    ) {
                        break;
                    }

                    $this->set($for->var, new Number($start, ''));
                    $start += $d;

                    $ret = $this->compileChildren($for->children, $out);

                    if ($ret) {
                        if ($ret[0] !== Type::T_CONTROL) {
                            return $ret;
                        }

                        if ($ret[1]) {
                            break;
                        }
                    }
                }
                break;

            case Type::T_BREAK:
                return [Type::T_CONTROL, true];

            case Type::T_CONTINUE:
                return [Type::T_CONTROL, false];

            case Type::T_RETURN:
                return $this->reduce($child[1], true);

            case Type::T_NESTED_PROPERTY:
                list(, $prop) = $child;

                $prefixed = [];
                $prefix = $this->compileValue($prop->prefix) . '-';

                foreach ($prop->children as $child) {
                    switch ($child[0]) {
                        case Type::T_ASSIGN:
                            array_unshift($child[1][2], $prefix);
                            break;

                        case Type::T_NESTED_PROPERTY:
                            array_unshift($child[1]->prefix[2], $prefix);
                            break;
                    }

                    $prefixed[] = $child;
                }

                $this->compileChildrenNoReturn($prefixed, $out);
                break;

            case Type::T_INCLUDE:
                // including a mixin
                list(, $name, $argValues, $content) = $child;

                $mixin = $this->get(self::$namespaces['mixin'] . $name, false);

                if (! $mixin) {
                    $this->throwError("Undefined mixin $name");
                    break;
                }

                $callingScope = $this->getStoreEnv();

                // push scope, apply args
                $this->pushEnv();
                $this->env->depth--;

                if (isset($content)) {
                    $content->scope = $callingScope;

                    $this->setRaw(self::$namespaces['special'] . 'content', $content, $this->env);
                }

                if (isset($mixin->args)) {
                    $this->applyArguments($mixin->args, $argValues);
                }

                $this->env->marker = 'mixin';

                $this->compileChildrenNoReturn($mixin->children, $out);

                $this->popEnv();
                break;

            case Type::T_MIXIN_CONTENT:
                $content = $this->get(self::$namespaces['special'] . 'content', false, $this->getStoreEnv())
                         ?: $this->get(self::$namespaces['special'] . 'content', false, $this->env);

                if (! $content) {
                    $this->throwError('Expected @content inside of mixin');
                    break;
                }

                $storeEnv = $this->storeEnv;
                $this->storeEnv = $content->scope;

                $this->compileChildrenNoReturn($content->children, $out);

                $this->storeEnv = $storeEnv;
                break;

            case Type::T_DEBUG:
                list(, $value) = $child;

                $line = $this->sourceLine;
                $value = $this->compileValue($this->reduce($value, true));
                fwrite($this->stderr, "Line $line DEBUG: $value\n");
                break;

            case Type::T_WARN:
                list(, $value) = $child;

                $line = $this->sourceLine;
                $value = $this->compileValue($this->reduce($value, true));
                echo "Line $line WARN: $value\n";
                break;

            case Type::T_ERROR:
                list(, $value) = $child;

                $line = $this->sourceLine;
                $value = $this->compileValue($this->reduce($value, true));
                $this->throwError("Line $line ERROR: $value\n");
                break;

            case Type::T_CONTROL:
                $this->throwError('@break/@continue not permitted in this scope');
                break;

            default:
                $this->throwError("unknown child type: $child[0]");
        }
    }

    // results the file path for an import url if it exists
    public function findImport($url)
    {
        $urls = array();

        // for "normal" scss imports (ignore vanilla css and external requests)
        if (!preg_match('/\.css|^http:\/\/$/', $url)) {
            // try both normal and the _partial filename
            $urls = array($url, preg_replace('/[^\/]+$/', '_\0', $url));
        }

        foreach ($this->importPaths as $dir) {
            if (is_string($dir)) {
                // check urls for normal import paths
                foreach ($urls as $full) {
                    $full = $dir .
                        (!empty($dir) && substr($dir, -1) != '/' ? '/' : '') .
                        $full;

                    if ($this->fileExists($file = $full.'.scss') ||
                        $this->fileExists($file = $full)
                        || $this->fileExists($file = $full.'.css')
					) //addon by surikat
                    {
                        return $file;
                    }
                }
            } elseif (is_callable($dir)) {
                // check custom callback for import path
                $file = call_user_func($dir, $url, $this);

                if ($file !== null) {
                    return $file;
                }
            }
        }

        return null;
    }
    
    protected function autoGenerateFont($f){ //added by surikat
		foreach($this->importPaths as $path){
			if(
				is_file($path.'/font/'.$f.'.scss')
				||is_file($path.'/font/_'.$f.'.scss')
				||is_file($path.'/font/'.$f.'.css')
			) return;
		}
		foreach($this->importPaths as $path){
			$ttf = is_file($path.'/../font/'.$f.'.ttf');
			if($ttf)
				$this->makeFontFromTTF($path.'/../font/',$f);
			$eot = is_file($path.'/../font/'.$f.'.eot');
			$woff = is_file($path.'/../font/'.$f.'.woff');
			$woff2 = is_file($path.'/../font/'.$f.'.woff2');
			$svg = is_file($path.'/../font/'.$f.'.svg');
			if($ttf||$eot||$woff||$woff2||$svg){
				$fontName = ucwords(str_replace('-',' ',$f));
				$fontFace = '@font-face {
	font-family: \''.$fontName.'\';
';
				if($eot)
					$fontFace .= '	src: url(\'#{$font}'.$f.'.eot\');';
				$fontFace .= "\n	src: ";
				$n = false;
				if($eot){
					if($n)
						$fontFace .= "\n";
					$n = true;
					$fontFace .= '		url(\'#{$font}'.$f.'.eot?#iefix\') format(\'embedded-opentype\'),';
				}
				if($woff2){
					if($n)
						$fontFace .= "\n";
					$n = true;
					$fontFace .= '		url(\'#{$font}'.$f.'.woff2\') format(\'woff2\'),';
				}
				if($woff){
					if($n)
						$fontFace .= "\n";
					$n = true;
					$fontFace .= '		url(\'#{$font}'.$f.'.woff\') format(\'woff\'),';
				}
				if($ttf){
					if($n)
						$fontFace .= "\n";
					$n = true;
					$fontFace .= '		url(\'#{$font}'.$f.'.ttf\') format(\'truetype\'),';
				}
				if($svg){
					if($n)
						$fontFace .= "\n";
					$n = true;
					$svg = simplexml_load_file($path.'/../font/'.$f.'.svg');
					$svgid = '';
					foreach($svg as $v){
						if($v->font&&($svgid=$v->font['id']))
							break;
					}
					$fontFace .= '		url(\'#{$font}'.$f.'.svg#'.$svgid.'\') format(\'svg\'),';
				}
				$fontFace = rtrim($fontFace,',');
				$fontFace .= ';
	font-weight: normal;
	font-style: normal;
}';
				$dir = $path.'/font/';
				if(!is_dir($dir))
					@mkdir($dir,0777,true);
				file_put_contents($dir.$f.'.scss',$fontFace);
				return;
			}
		}
	}
	
	public $bin_ttf2eot;
	public $bin_fontforge;
	public $bin_sfnt2woff;
	public $bin_woff2_compress;
	protected function makeFontFromTTF($dir,$f){
		$path = $dir.'/'.$f.'.ttf';
		$path = realpath($path);
		$base = basename($path);
		
		if(!$path)
			return;
		
		if(!isset($this->bin_ttf2eot)&&is_file('/usr/bin/ttf2eot'))
			$this->bin_ttf2eot = '/usr/bin/ttf2eot';
		if(!isset($this->bin_fontforge)&&is_file('/usr/bin/fontforge'))
			$this->bin_fontforge = '/usr/bin/fontforge';
		if(!isset($this->bin_sfnt2woff)&&is_file('/usr/bin/sfnt2woff'))
			$this->bin_sfnt2woff = '/usr/bin/sfnt2woff';
		if(!isset($this->bin_woff2_compress)&&is_file('/usr/bin/woff2_compress'))
			$this->bin_woff2_compress = '/usr/bin/woff2_compress';
		
		$eotPath = $dir.'/'.$f.'.eot';
		$woffPath = $dir.'/'.$f.'.woff';
		$woff2Path = $dir.'/'.$f.'.woff2';
		$svgPath = $dir.'/'.$f.'.svg';

		if($this->bin_fontforge){
			exec($this->bin_fontforge.' -script '.__DIR__.'/scripts-fontforge/tottf.pe "'.$path.'"',$output,$return);
			if(0!==$return)
				throw new \RuntimeException('Fontforge could not convert '.$base.' to TrueType format.');
		}
		if(!is_file($eotPath)&&$this->bin_ttf2eot){
			exec($this->bin_ttf2eot.' "'.$path. '" > ' . $eotPath.'',$output,$return);
			$outputHuman = implode('<br/>', $output);
			if(0!==$return)
				throw new \RuntimeException('ttf2eot could not convert '.$base.' to EOT format.' . $outputHuman);
		}
		if(!is_file($svgPath)&&$this->bin_fontforge){
			exec($this->bin_fontforge.' -script '.__DIR__.'/scripts-fontforge/tosvg.pe "'.$path.'"',$output,$return);
			if(0!==$return)
				throw new \RuntimeException('Fontforge could not convert '.$base.' to SVG format.');
		}
        
        if(!is_file($woffPath)&&$this->bin_sfnt2woff){
			exec($this->bin_sfnt2woff.' "'.$path.'"',$output,$return);
			if(0!==$return)
				throw new \RuntimeException('sfnt2woff could not convert '.$base.' to Woff format.');
		}
		if(!is_file($woff2Path)&&$this->bin_woff2_compress){	
			exec($this->bin_woff2_compress.' "'.$path.'"',$output,$return);
			if(0!==$return)
				throw new \RuntimeException('woff2_compress could not convert '.$base.' to Woff2 format.');
		}
	}
}
