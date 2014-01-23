<?php

// Modified for 1.5 compatibility by Jack McDade

require_once('phpsass/SassParser.php');
class Plugin_sass extends Plugin {

  var $meta = array(
    'name'       => 'SASS',
    'version'    => '0.1.1',
    'author'     => 'Jeremy Messenger',
    'author_url' => 'http://jlmessenger.com'
  );

  var $output_styles = array(
    'compressed' => SassRenderer::STYLE_COMPRESSED,
    'compact'    => SassRenderer::STYLE_COMPACT,
    'expanded'   => SassRenderer::STYLE_EXPANDED,
    'nested'     => SassRenderer::STYLE_NESTED
  );

  function __construct() {
    parent::__construct();
    $this->theme_root = Config::getTemplatesPath();
    $this->site_root  = Config::getSiteRoot();
  }

  public function index() {
    $dev = $this->fetchParam('dev', FALSE) == "true";
    
    $def_style = $dev ? 'nested' : 'compact';
    $def_update_on = $dev ? 'always' : 'dir';
    $def_on_error = $dev ? 'die' : 'ignore';
    
    $src = $this->fetchParam('src', NULL);
    $style = $this->fetchParam('style', $def_style);
    $update_on = $this->fetchParam('update', $def_update_on);
    $debug_info = $this->fetchParam('debug_info', FALSE) == "true";
    $line_numbers = $this->fetchParam('line_numbers', FALSE) == "true";
    $on_error = $this->fetchParam('error', $def_on_error);
    
    if ($src == null) {
      return $this->error_output($on_error, 'SASS src parameter required');
    }
    
    $input_file_path = $this->theme_root . ltrim($src);
    
    if ( ! file_exists($input_file_path)) {
      return $this->error_output($on_error, 'SASS src file not found');
    }
    
    if ( ! array_key_exists($style, $this->output_styles)) {
      $style = 'compact';
    }
    $output_style = $this->output_styles[$style];
    
    $input_name = basename($src);
    $input_dir = dirname($input_file_path);
    
    $dot = strrpos($input_name, '.');
    $prefix = $dot ? substr($input_name, 0, $dot) : $name;
    $ext = $dot ? substr($input_name, $dot) : '.sass';
    
    $output_file_path = '_cache/css/'.$prefix.'.css';
    
    $input_last_updated = filemtime($input_file_path);
    $file_topper = '/* Statamic SASS Plugin ('.$input_last_updated.' '.$output_style.") */\n";
    
    $output_file_exists = file_exists($output_file_path);
    if ($output_file_exists) {
      $output_last_updated = filemtime($output_file_path);
    } else {
      $output_last_updated = -1;
      $outdir = dirname($output_file_path);
      if ( ! is_dir($outdir)) {
        mkdir($outdir, 0777, true);
      }
    }
    
    $write_css = FALSE;
    
    switch  ($update_on) {
      case 'always':
        $write_css = TRUE;
        break;
      case 'file':
        $write_css = $input_last_updated > $output_last_updated;
        break;
      default: // 'dir':
        $ls = scandir($input_dir);
        $input_dir_updated = 0;
        if (is_array($ls)) {
          foreach ($ls as $file) {
            if ($file[0] != '.' && substr_compare($file, $ext, -5, 5, true) == 0) {
              $time = filemtime($input_dir.'/'.$file);
              if ($time > $input_dir_updated) {
               $input_dir_updated = $time;
              }
            }
          }
        }
        $write_css = $input_dir_updated > $output_last_updated;
    }
    
    if ($write_css) {
      $options = array(
        'basepath'     => $input_dir,
        'filename'     => array('dirname' => dirname($src), 'basename' => $input_name),
        'style'        => $output_style,
        'debug_info'   => $debug_info,
        'line_numbers' => $line_numbers
      );
      
      $parser = new SassParser($options);
      
      try {
        $css = $parser->toCss($input_file_path);
      } catch(SassException $ex) {
        $css = FALSE;
        $output = $this->error_output($on_error, ''.$ex);
        if ($output != '' || ! $output_file_exists) {
          return $output;
        }
      }
      
      if ($css) {
        $fp = fopen($output_file_path, 'w');
        fwrite($fp, $file_topper);
        fwrite($fp, $css);
        fclose($fp);
      }
    }// else - cached output is valid
    
    return $this->site_root.$output_file_path;
  }

  private function error_output($on_error, $msg) {
    switch ($on_error) {
      case 'echo':
        echo nl2br($msg);
        return '';
      case 'die':
        die(nl2br($msg));
      default: // case 'ignore':
        return '';
    }
  }
}
