<?php
/*
Plugin Name: Import External Images & Files
Description: Import External Images & Files From Posts or Products Description
Version:     1
Author:      Isaac Glikson
License:     GPLv3
Text Domain: import-external-files
*/

add_action('admin_menu', 'create_menu');

function create_menu()
{
    add_submenu_page('options-general.php', 'Import External', 'Import External', 'manage_options', __FILE__, 'plugin_page');
    add_action('admin_init', 'register_settings');
    load_plugin_textdomain('import-external-files', false, 'import-external-files/languages');
}

function register_settings()
{
    register_setting('settings-group', 'importexternalfiles_import_site_url');
    register_setting('settings-group', 'importexternalfiles_import_file_types');
}

function plugin_page()
{
?>
<div class="wrap">
<h2><?php
    echo __('Import External Images & Files', 'import-external-files');
?></h2>
<h3>1</h3>
<form method="post" action="options.php">
    <?php
    settings_fields('settings-group');
?>
    <?php
    do_settings_sections('settings-group');
    $files_types = get_option('importexternalfiles_import_file_types');
    if (!$files_types) {
        $files_types = "bmp|jpg|jpeg|gif|png|zip|pdf|csv|xls|pps";
        
    }
?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row"><?php
    echo __("Import URL'S Starting With", 'import-external-files');
?>: *</th>
        <td><input type="text" title=http://website.com style=width:400px; dir=ltr name="importexternalfiles_import_site_url" value="<?php
    echo esc_attr(get_option('importexternalfiles_import_site_url'));
?>" /></td>
        </tr>
        <tr valign="top">
        <th scope="row"><?php
    echo __('File Types', 'import-external-files');
?>:</th>
        <td><input type="text" style=width:400px; dir=ltr name="importexternalfiles_import_file_types" value="<?php
    echo $files_types;
?>" /></td>
        </tr>
    </table>
    
    <?php
    submit_button();
?></form>
<hr>
<h3>2</h3>
<form method="post" action="" method=get id=frm_action name=frm_action>
<input type=hidden name=action value="import">

<?php
    if ($_POST['action'] == "import") {
 
        ini_set('max_execution_time', 0);
        include(realpath(dirname(__FILE__)) . "/simple_php_dom.php");
        $upload_dir = wp_upload_dir();

        $still_working = file_get_contents(realpath(dirname(__FILE__)) . "/status.txt");
        if ($still_working < 100) {
            die();
        }

        $context = stream_context_create(array(
            'http' => array(
                'header' => 'Connection: close\r\n'
            )
        ));
        global $post;
        $args    = array(
            'post_type' => 'any',
            'numberposts' => -1
        );
        $myposts = get_posts($args);

        $total_posts = count($myposts);
        file_put_contents(realpath(dirname(__FILE__)) . "/status.txt", 0);
        
        foreach ($myposts as $post) {
            $cur_counter++;
            $cur_status = intval((100 / $total_posts) * $cur_counter);
            
            file_put_contents(realpath(dirname(__FILE__)) . "/status.txt", $cur_status);
            if (trim($post->post_content) !== "") {
                $post_content = apply_filters('the_content', $post->post_content);
                $post_title   = apply_filters('the_title', $post->post_title);
                $html         = str_get_html($post_content);
                foreach ($html->find('img[src^="' . esc_attr(get_option('importexternalfiles_import_site_url')) . '"]') as $element) {
                    
                    $ext = pathinfo($element->src, PATHINFO_EXTENSION);
                    if (preg_match('/' . $files_types . '/i', $ext)) {
                        $ar_files[] = $element->src;
                    }
                    
                }
                
                
                foreach ($html->find('a[src^="' . esc_attr(get_option('importexternalfiles_import_site_url')) . '"]') as $element) {
                    $ext = pathinfo($element->href, PATHINFO_EXTENSION);
                    if (preg_match('/' . $files_types . '/i', $ext)) {
                        $ar_files[] = $element->href;
                    }
                    
                }
                
                
                foreach ($ar_files as $element) {
                    
                    echo $post->post_title . " - " . $element . "<br>";
                    // save file
                    
                    $filename = $post->ID . "-" . basename($element);
                    
                    if (wp_mkdir_p($upload_dir['path'])) {
                        $file = $upload_dir['path'] . '/' . $filename;
                    } else {
                        $file = $upload_dir['basedir'] . '/' . $filename;
                    }
                    
                    $image_data = file_get_contents($element, false, $context);
                    
                    file_put_contents($file, $image_data);
                    
                    // check type
                    $wp_filetype = wp_check_filetype($filename, null);
                    
                    // update media
                    $attachment = array(
                        'post_mime_type' => $wp_filetype['type'],
                        'post_title' => sanitize_file_name($filename),
                        'post_content' => '',
                        'post_status' => 'inherit'
                    );
                    
                    $attach_id = wp_insert_attachment($attachment, $file, $post->ID);
                    
                    // Generate the metadata for the attachment, and update the database record.
                    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
                    wp_update_attachment_metadata($attach_id, $attach_data);
                    
                    // echo post and file to screen
                    $current_url  = $upload_dir['url'] . '/' . $filename;
                    $current_url  = str_replace("http://" . $_SERVER['SERVER_NAME'], '', $current_url);
                    $post_content = str_replace($element, $current_url, $post_content);
                    
                    
                }
            }
            
            
            $my_post = array(
                'ID' => $post->ID,
                'post_content' => $post_content
            );
            
            wp_update_post($my_post);
            
        }
        
    }
    
    
?>

<style>
#myProgress {
display:none;
    background-color: #ddd;
    height: 29px;
    margin: 25px 0;
    position: relative;
    width: 50%;
}

#myBar {
  position: absolute;
  width: 10%;
  height: 100%;
  background-color: #4CAF50;
}

#label {
  text-align: center;
  line-height: 30px;
  color: white;
}
</style>
<script src="http://malsup.github.com/jquery.form.js"></script> 
<script>
function move() {

  var elem = document.getElementById("myBar");   
  var width = 0;

  setTimeout(function(){frame();}, 10);  
 
  jQuery("#frm_action").ajaxSubmit();
  jQuery("#myProgress").show();


  function frame() {
    if (width >= 100) {
 
    } else {
      width++; 

  jQuery.ajax({
   url: "<?php echo plugin_dir_url(__FILE__);?>status.txt?rand=" + new Date().getTime() ,
   async: false,
   success: function (data){

      width = data;
      elem.style.width = width + '%'; 
      document.getElementById("label").innerHTML = width * 1  + '%';

      setTimeout(function(){frame();}, 10);
   
   }
        });

 
    }
  }
}
</script>

<br>
<div id=wrap_import>
<input name="submit" id="submit"  class="button button-primary progress_button" value="<?php echo __("Import All", 'import-external-files')?>"  onclick="move();return false"
 type="button">

 

<div id="myProgress">
  <div id="myBar">
    <div id="label"></div>
  </div>
</div>


</div>
</form>

 
</div>

<?php
}
?>
