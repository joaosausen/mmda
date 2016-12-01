#!/usr/bin/php
<?php
{
  $working_directory = getcwd();

  /**
   * Help function.
   */
  function mmda_help() {
    print " mmda.com.br \n";
    print " Usage:\n";
    print "  status                print some STATUS information\n";
    print "  env [name]            switch ENVironments.\n";
    print "  bn [nids]             Backups Node(s)\n";
    print "  rn [nids]             Restore Node(s)\n";
    print "  cn [nids]             Clone Node(s)\n";
    print "  lt [url]              List Templates for [page]\n";
    print "  ru [module] [N]       Run Update\n";
    print "  help                  print this HELP.\n";
    print "  generate-config       generates a config file example.\n";
  }

  /**
   * Return file location recursively.
   */
  function get_recursive($filename) {
    chdir($working_directory);
    while (!file_exists($filename) && getcwd() != '/') {
      chdir('..');
    }
    if (file_exists('index.php')) {
      return getcwd();
    }
    return FALSE;
  }

  // Discover Drupal root.
  chdir($working_directory);
  while (!file_exists('index.php') && getcwd() != '/' && !file_exists('includes/bootstrap.inc')) {
    chdir('..');
  }
  if (!file_exists('index.php') || !file_exists('includes/bootstrap.inc')) {
    print " mmda needs a higher bootstrap level of Drupal.\n\n";
    return;
  }
  else {
    $drupal_root = getcwd();
    define('DRUPAL_ROOT', $drupal_root);
  }

  /**
   * Return the mmda folder location.
   */
  function _mmda_folder($subfolder = FALSE) {
    // Creates root if doesn't exists.
    $path = DRUPAL_ROOT . '/.mmda';
    if (!file_exists($path)) {
      mkdir($path, 0777);
    }
    if ($subfolder) {
      $path = DRUPAL_ROOT . '/.mmda/' . $subfolder;
      if (!file_exists($path)) {
        mkdir($path, 0777);
      }
    }
    return $path;
  }

  /**
   * Generates a config file.
   */
  function mmda_generate_config() {
    $path = DRUPAL_ROOT . '/mmda.config';
    if (file_exists($path)) {
      print " A mmda.config file already exists at " . DRUPAL_ROOT . "/ \n\n";
      return;
    }
    
    $content = "<?php
/**
 * To configure the modules enabled/disabled in a environment switch, just
 * follow the array structure below.
 *
 * \$environments = array(
 *   'dev' => array(
 *     'modules' => array(
 *       'devel',
 *       'views_ui',
 *     ),
 *   ),
 *   'prod' => array(
 *     'modules' => array(
 *       'views_ui',
 *     ),
 *   ),
 * );
 *
 *
 * Additionally, if you need more configurations, you can define a
 * mmda_env_YOURENV() function. You can call mmda and drupal functions from this
 * file, if you for example wants to backup the nodes 1, 2 and 3 when switching
 * to dev environment, you can do:
 *
 * function mmda_env_dev() {
 *   mmda_bn(array(1, 2, 3));
 * }
 *
 * If there is a - on your environment name, it will be replaced by _.
 *
 * The mmda functions are called, mmda_COMMAND(array(arg1, arg2, ...)), so,
 * for example, to run an update it should be:
 *
 * mmda_ru(array('my_module', 7004));
 * 
 */";
    if (file_put_contents($path, $content)) {
      print " mmda.config file generated at " . DRUPAL_ROOT . "/ \n\n";
    }
    else {
      print " Error generating config file.\n\n";
    }
  }

  /**
   * Print status.
   */
  function mmda_status() {
    print 'Root: ' . DRUPAL_ROOT . "\n";
    print 'Drupal: ' . VERSION . "\n";
    print "\n";
  }

  /**
   * Run hook update N.
   */
  function mmda_ru($args) {
    if (!count($args)) {
      print " Usage : 'mmda ru hook_update_n' or 'mmda ru module_name N' \n Ex.: 'mmda ru my_module_update_7032' or 'mmda ru my_module 7032' \n\n";
      return;
    }

    if (count($args) > 1) {
      $module = $args[0];
      $hook = $module . '_update_' . $args[1];
    }
    else {
      $hook = reset($args);
      $module = implode('_', array_slice(explode('_', $hook), 0, -2));
    }

    if (!module_exists($module)) {
      print " Error: " . $module . " doesn't exists or is not enabled. \n\n";
      return;
    }
    
    module_load_include('install', $module);
    if (function_exists($hook)) {
      $hook();
      print "Update {$hook} run on {$module}. \n\n";
    }
    else {
      print " Error: This hook_update doesn't exists. \n\n";
    }
  }
  
  /**
   * Backups nodes.
   */
  function mmda_bn($args) {
    if (!count($args) || $args[0] == 'help') {
      print " Usage: mmda bn nids \nEx.: mmda bn 103 104 105\n\n";
      return;
    }

    $backups = _mmda_folder('backups');
    foreach ($args as $nid) {
      $node = node_load($nid);
      if (!$node) {
        print " Error: Invalid nid '" . $nid . "'\n";
      }
      else {
        $content = serialize($node);
        $path = $backups . '/' . $node->nid . ".node";
        print "Node '" . $node->title ."' saved to '" . $path . "'\n";
        file_put_contents($path . $filename, $content);
      }
    }
    print "\n";
  }

  /**
   * Restore nodes.
   */
  function mmda_rn($args) {
    if (!count($args) || $args[0] == 'help') {
      print " Usage: mmda bn nid \n Ex.: mmda bn 103 104\n\n";
      return;
    }

    foreach ($args as $nid) {
      $path = _mmda_folder('backups') . '/' . $nid . ".node";
      if (!file_exists($path)) {
        print " File '" . $path . "' not found. Skipping. \n";
        continue;
      }
      $node = unserialize(file_get_contents($path));
      if ($node) {
        print " Recovering node '" . $node->title . "'\n";
        node_save($node);
      }
      else {
        print " An error ocurred recovering the node.\n";
      }
    }
    print "\n";
  }

  /**
   * Clone node.
   */
  function mmda_cn($args) {
    if (!count($args) || $args[0] == 'help') {
      print " Usage: mmda cn nid \n Ex.: mmda bn 103 104\n\n";
      return;
    }

    foreach ($args as $nid) {
      $node = node_load($nid);
      if (!$node) {
        print " Error: Invalid nid '" . $nid . "'\n";
      }
      else {
        $title = $node->title;
        unset($node->nid);
        unset($node->uuid);
        unset($node->vid);
        unset($node->vuuid);
        $node->is_new = TRUE;
        $node->title = 'Clone of ' . $node->title;
        node_save($node);
        print "Node '" . $title ."' cloned.\n";
      }
    }
    print "\n";
  }

  // Return the templates from a page.
  function _mmda_get_templates($page) {
    $initial_value = variable_get('theme_debug', FALSE);
    variable_set('theme_debug', TRUE);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.6) Gecko/2009011913 Firefox/3.0.6');
    curl_setopt($ch, CURLOPT_URL, $page);
    //curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $html = curl_exec($ch);

    $lines = explode(PHP_EOL, $html);
    
    $collect = FALSE;
    $templates = array();
    $key = 0;
    $remove = array(
      "<!-- THEME DEBUG -->",
      "<!-- CALL: theme('",
      "') -->",
      "<!-- FILE NAME SUGGESTIONS:",
      "<!-- BEGIN OUTPUT from '",
      "' -->",
      "-->",
      "* ",
      "x ",
    );
        
    foreach ($lines as $n => $line) {
      if (strpos(trim($line), '<!-- THEME DEBUG -->') === 0) {
        $collect = TRUE;
      }

      if ($collect) {
        $output = trim(str_replace($remove, '', $line));
        
        if (strpos(trim($line), "<!-- CALL: theme('") === 0) {
          $templates[$key]['theme'] = $output;
        }

        if (strpos(trim($line), "* ") === 0) {
          $templates[$key]['suggestions'][] = $output;
        }

        if (strpos(trim($line), "x ") === 0) {
          $templates[$key]['chosen'] = $output;
        }

        if (strpos(trim($line), "<!-- BEGIN OUTPUT from ") === 0) {
          $templates[$key]['path'] = $output;
        }

        if (!empty($output)) {
          // print $output . "\n";
        }
      }

      if (strpos(trim($line), '<!-- BEGIN OUTPUT from') === 0) {
        $collect = FALSE;
        $key++;
      }

    }
    variable_set('theme_debug', $initial_value);
    return $templates;
  }
  
  // List templates.
  function mmda_lt($args) {
    if (!count($args) || $args[0] == 'help') {
      print " Usage: mmda lt [url] \n Ex.: mmda lt mysite.dev/somepage \n\n";
      print " This will only works if the page has theme_debug variable set to\n";
      print " true, the script will try to change this to one if you are in a\n";
      print " drupal folder or subfolder, keep in mind the url should correponds\n";
      print " to a url for the folder you are on right now.\n\n";
      return;
    }

    $templates = _mmda_get_templates($args[0]);

    foreach ($templates as $template) {
      print $template['theme'] . " (" . $template['path'] . ")\n";
      print " x " . $template['chosen'] . "\n";
      print " * " . implode("\n * ", $template['suggestions']) . "\n";
      print "\n";
    }
    print "\n";
    
  }

  function mmda_gt($args) {
    if (!count($args) || $args[0] == 'help') {
      print " Usage: mmda gt [url] \n Ex.: mmda gt mysite.dev/somepage \n\n";
      print " This will only works if the page has theme_debug variable set to\n";
      print " true, the script will try to change this to one if you are in a\n";
      print " drupal folder or subfolder, keep in mind the url should correponds\n";
      print " to a url for the folder you are on right now.\n\n";
      print " The template will be copied to your active theme folder.";
      return;
    }

    $templates = _mmda_get_templates($args[0]);

    $path = DRUPAL_ROOT . '/' . path_to_theme();
    $templates_dir = $path . '/templates';
    if (!file_exists($templates_dir)) {
      mkdir($templates_dir);
      print " Creating directory templates on {$templates_dir}\n";
    }
    
    foreach ($templates as $template) {

      $dir = $templates_dir . '/' . $template['theme'];
      if (!file_exists($dir)) {
        mkdir($dir);
        print " Creating directory {$template['theme']} on $dir\n";
      }

      $source = DRUPAL_ROOT . '/' . $template['path'];
      $suggestion = reset($template['suggestions']);
      $dest = $dir . '/' . $suggestion;
      if (copy($source, $dest)) {
        print " Creating file {$dest}\n";
      }
      else {
        print "Error copying {$source} to {$dest}\n";
      }
    }
    print "\n";
  }
  
  /**
   * Switch environments.
   */
  function mmda_env($args) {
    $mmda_config = get_recursive('mmda.config');
    if (!$mmda_config) {
      print " No mmda.config file found.\n Generate one with mmda generate-config \n";
    }
    else {
      @include 'mmda.config';
      if (!count($args)) {
        print " Environments: " . implode(', ', array_keys($environments)) . "\n";
      }
      else {
        $env = reset($args);
        if (!isset($environments[$env])) {
          print " Environment {$env} doesn't exists. The available environments are '";
          print implode(', ', array_keys($environments)) . "'\n";
        }
        else {
          print " Switching to '{$env}'. \n";
          $enable = array();
          $disable = array();
          foreach ($environments as $name => $info) {
            foreach ($info['modules'] as $module) {
              if ($name == $env) {
                $enable[] = $module;
              }
              else {
                $disable[] = $module;
              }
            }
          }
          $disable = array_diff($disable, $enable);
          // Disable.
          if (count($disable)) {
            module_disable($disable);
            drupal_uninstall_modules(array_reverse($disable));
            print "  Disabling and uninstalling '" . implode(', ', $disable) . "'\n";
          }          
          // Enable.
          module_enable($enable);
          print "  Enabling '" . implode(', ', $enable) . "'\n";
          // Call function to run after env switch if it exists.
          $function = 'mmda_env_' . str_replace('-', '_', $env);
          if (function_exists($function)) {
            $function();
          }
        }
      }
    }
    print "\n";
  }
  
  if (count($argv) < 2) {
    mmda_help();
    return;
  }

  // Bootstrap on drupal, drupal_override_server_variables() prevents notices
  // from missing $_SERVER variables.
  require_once 'includes/bootstrap.inc';
  drupal_override_server_variables();
  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

  // Arguments handler.
  $arg = $argv[1];

  // Remove the filename and the first argument.
  $args = $argv;
  unset($args[0]);
  unset($args[1]);

  $function = 'mmda_' . str_replace('-', '_', $arg);
  if (function_exists($function)) {
    // Pass the values reseting the keys.
    $function(array_values($args));
  }
  else {
    mmda_help();
  }
}
