<?

	// yotsuba plugin API

	// add a function $function to the list of callbacks named by $hook.
	function register_callback($hook, $function) {
		global $HOOKS;
		if( !isset($HOOKS[$hook]) )
			$HOOKS[$hook] = array($function);
		else
			array_push($HOOKS[$hook], $function);
	}

	// run all the callback functions associated with the name $hook.
	// they will be passed $args.
	function run_callback($hook, $args) {
		global $HOOKS;
		if(isset($HOOKS[$hook]))
			foreach($HOOKS[$hook] as $function)
				if(function_exists($function))
					$function($args);
	}

	// load plugins
	if(defined('PLUGIN_DIR'))
		foreach(explode(',', PLUGINS) as $plugin)
			include_once PLUGIN_DIR . trim($plugin) . '.php';
?>