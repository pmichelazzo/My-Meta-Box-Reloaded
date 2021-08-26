<?php
/**
 * Easy WP Metabox Class
 *
 * This class is used to create meta boxes with fields inside of any WordPress
 * content type (custom or not) without the hard job to code the meta boxes and
 * fields. to do that, you just need to call the class, set some parameters for
 * the meta boxes, write the fields passing some parameters and, that's it, the
 * job is done!
 *
 * This code has a long story. First, is derived from Meta Box script by Rilwis
 * (rilwis@gmail.com) version 3.2, which later was forked by Cory Crowley
 * (cory.ivan@gmail.com) and later on, forked by Raz Ohad (https://github.com/bainternet).
 * One day, looking for an easy class to finish a job to a client, I found the 
 * last fork but, since the repo looks "abandoned" and I need some changes to
 * accomplish my tasks, I decided to rebuild the class, adding new features,
 * remove old ones and make the code better and reliable. But, of course, I need
 * to say a big "Thank you" to the previous authors for the job done.
 *
 * @version 1.0
 * @copyright 2021
 * @author Paulino Michelazzo (michelazzo@me.com)
 * @link https://github.com/pmichelazzo/easy-wp-metabox
 *
 * @license GNU General Public LIcense v3.0 - license.txt
 * @package Easy WP Metabox Class
 */

if (!class_exists('EasyMetaBox')) :
/**
 * All Types Meta Box class.
 *
 * @package Easy WP Meta Box Class
 * @since 1.0
 */
class EasyMetaBox {
  /**
   * Holds the meta box object.
   *
   * @var object
   * @access protected
   */
  protected $_meta_box;

  /**
   * Holds meta box fields.
   *
   * @var array
   * @access protected
   */
  protected $_prefix;

  /**
   * Holds prefix for meta box fields.
   *
   * @var array
   * @access protected
   */
  protected $_fields;

  /**
   * Paths to allow themes as well as plugins.
   *
   * @var string
   * @access protected
   */
  protected $_path;

  /**
   * Use local images.
   *
   * @var bool
   * @access public
   */
  public $_local = false;

  /**
   * Holds used field types.
   * 
   * @var array
   * @access public
   */
  public $field_types = array();

  /**
   * Holds grouping boolean.
   * 
   * @var boolean
   * @access public
   */
  public $inGroup = false;

  /**
   * The constructor.
   *
   * @access public
   * @param array $meta_box
   */
  public function __construct ($meta_box) {
    // If is not the admin area, exit.
    if (!is_admin()) {
      return;
    }

    // Load the translation.
    add_filter('init', [$this, 'load_textdomain']);

    // Assign meta box values to local variables and add the missed values.
    $this->_meta_box = $meta_box;
    $this->_fields = $this->_meta_box['fields'];
    $this->add_missed_values();

    // Generate the working path based on the user parameter.
    if ($meta_box['use_with_theme'] === true) {
      $this->_path = get_stylesheet_directory_uri() . '/easy-wp-metabox';
    }
    elseif ($meta_box['use_with_theme'] === false) {
      $this->_path = plugins_url('easy-wp-metabox', plugin_basename(dirname(__FILE__)));
    }
    else {
      $this->_path = $meta_box['use_with_theme'];
    }

    // Add meta boxes
    add_action('add_meta_boxes', array($this, 'add'));
    add_action('save_post', array($this, 'save'));
    // Load common asset files.
    add_action('admin_print_styles', array($this, 'load_scripts_styles'));
    // Limit File type at upload
    add_filter('wp_handle_upload_prefilter', array($this,'Validate_upload_file_type'));
  }


  /**
   * Load the JavaScript and CSS assets.
   *
   * @since 1.0
   * @access public
   */
  public function load_scripts_styles() {
    // Assets are loaded when needed
    global $typenow;
    if (in_array($typenow, $this->_meta_box['pages']) && $this->is_edit_page()) {
      // Enqueue Meta Box Style
      wp_enqueue_style('easy-wp-metabox', $this->_path . '/css/easy-wp-metabox.css');
      // Enqueue Meta Box Scripts
      wp_enqueue_script('easy-wp-metabox', $this->_path. '/js/easy-wp-metabox.js', ['jquery'], null, true);
      wp_enqueue_script('repeater', $this->_path . '/js/repeater.js', ['jquery'], null, true);
      // Make upload feature work event when custom post type doesn't support 'editor'
      if ($this->has_field('image') || $this->has_field('file')) {
        wp_enqueue_script('media-upload');
        add_thickbox();
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-sortable');
      }
      // Check some fields that need special actions for them.
      foreach (['upload', 'color', 'date', 'time', 'code', 'select'] as $type) {
        call_user_func([$this, 'check_field_' . $type]);
      }
    }
  }

  /**
   * Enqueue jQuery select2 library to select fields.
   *
   * @since 1.0
   * @access public
   */
  public function check_field_select() {
    wp_enqueue_style('easy-multiselect-select2-css', $this->_path . '/js/select2/select2.css', [], null);
    wp_enqueue_script('easy-multiselect-select2-js', $this->_path . '/js/select2/select2.js', ['jquery'], false, true);
  }

  /**
   * Check the upload field and add actions.
   *
   * @since 1.0
   * @access public
   */
  public function check_field_upload() {
    add_action('post_edit_form_tag', [$this, 'add_enctype']);
  }

  /**
   * Add data encoding type for file upload.
   *
   * @since 1.0
   * @access public
   */
  public function add_enctype () {
    printf('enctype="multipart/form-data" encoding="multipart/form-data"');
  }

  /**
   * Enqueue assets to color fields.
   *
   * @since 1.0
   * @access public
   */
  public function check_field_color() {
    if ($this->is_edit_page()) {
      wp_enqueue_style('wp-color-picker');
      wp_enqueue_script('wp-color-picker');
    }
  }

  /**
   * Enqueue jQuery UI to date fields.
   *
   * @since 1.0
   * @access public
   */
  public function check_field_date() {
    if ($this->is_edit_page()) {
      $path = $this->_path . '/js/jquery-ui/';
      wp_enqueue_style('easy-jquery-ui-css', $path . 'jquery-ui.css');
      wp_enqueue_script('jquery-ui');
      wp_enqueue_script('jquery-ui-datepicker');
    }
  }

  /**
   * Enqueue jQuery UI to time fields.
   *
   * @since 1.0
   * @access public
   */
  public function check_field_time() {
    if ($this->is_edit_page()) {
      $path = $this->_path . '/js/jquery-ui/';
      wp_enqueue_style('easy-jquery-ui-css', $path .'jquery-ui.css');
      wp_enqueue_script('jquery-ui');
      wp_enqueue_script('easy-timepicker', $path .'jquery-ui-timepicker-addon.js', ['jquery-ui-slider', 'jquery-ui-datepicker'], false, true);
    }
  }

  /**
   * Enqueue assets to code fields.
   *
   * @since 1.0
   * @access public
   */
  public function check_field_code() {
    if ($this->is_edit_page()) {
      $path = $this->_path . '/js/codemirror/';
      wp_enqueue_style('easy-code-css', $path . 'codemirror.css', [], null);
      wp_enqueue_style('easy-code-css-dark', $path . 'solarizedDark.css', [], null);
      wp_enqueue_style('easy-code-css-light', $path . 'solarizedLight.css', [], null);
      wp_enqueue_script('easy-code-js', $path . 'codemirror.js', ['jquery'], false, true);
      wp_enqueue_script('easy-code-js-xml', $path . 'xml.js', ['jquery'], false, true);
      wp_enqueue_script('easy-code-js-javascript', $path . 'javascript.js', ['jquery'], false, true);
      wp_enqueue_script('easy-code-js-css', $path .'css.js', ['jquery'], false, true);
      wp_enqueue_script('easy-code-js-clike', $path .'clike.js', ['jquery'], false, true);
      wp_enqueue_script('easy-code-js-php', $path .'php.js', ['jquery'], false, true);
    }
  }

  /**
   * Add meta box for multiple post types.
   *
   * @since 1.0
   * @access public
   */
  public function add($postType) {
    if(in_array($postType, $this->_meta_box['pages'])) {
      add_meta_box(
        $this->_meta_box['id'],
        $this->_meta_box['title'],
        array($this, 'show'),
        $postType,
        $this->_meta_box['context'],
        $this->_meta_box['priority'],
      );
    }
  }

  /**
   * Callback to display fields in meta box.
   *
   * @since 1.0
   * @access public
   */
  public function show() {
    $this->inGroup = false;
    global $post;
    wp_nonce_field(basename(__FILE__), 'easy_meta_box_nonce');
    // Build the basic display structure.
    print '<div class="main-div">';
    foreach ($this->_fields as $field) {
      // When it's a multiple field (field containing fields), get all data.
      if (isset($field['multiple'])) {
        $has_more_fields = $field['multiple'];
      }
      else {
        $has_more_fields = false;
      }
      // Get the field content.
      $data = get_post_meta($post->ID, $field['id'], $has_more_fields);
      // If there's no data inside the field, get the default value (used when
      // the field is displayed on the first time).
      if (empty($data)) {
        $data = $field['std'];
      }
      else {
        $data = $data[0];
      }
      // If the file is not an image, repeater of file, call the WP esc_attr
      // function to clean the data.
      if (!in_array($field['type'], ['image', 'repeater','file'])) {
        $data = (is_array($data)) ? array_map('esc_attr', $data) : esc_attr($data);
      }
      // Call Separated methods for displaying each type of field.
      call_user_func([$this, 'show_field_' . $field['type']], $field, $data);
    }
    print '</div>';
  }

  /**
   * Show repeater fields.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_repeater($field, $data) {
    global $post;
    $this->show_field_begin($field);
    print '<div id="repeater">';
    print '<div class="add-button"><button type="button" class="repeater-add-btn">' . __('Add New Group', 'ewpmb') . '</button></div>';

    $submetas = get_post_meta($post->ID, $field['id'], true);
    // The saved data begins here.
    $c = 0;
    if (is_array($submetas) && count($submetas) > 0) {
      foreach ($submetas as $subdata) {
        print '<div class="items" data-group="' . $field['id'] . '">';
        print '<div class="item-content">';
        foreach ($field['fields'] as $f) {
          $id = $field['id'] .'[' . $c . '][' . $f['id'] . ']';
          if (isset($subdata[$f['id']])) {
            $content = $subdata[$f['id']];
          }
          else {
            $content = '';
          }
          // If the field doesn't have a value, fill with the standard value.
          if (empty($content)) {
            $content = $f['std'];
          }
          // if ('image' != $f['type'] && $f['type'] != 'repeater') {
          //   $subdata = is_array($subdata) ? array_map('esc_attr', $subdata) : esc_attr($subdata);
          // }
          call_user_func([$this, 'show_field_' . $f['type']], $f, $content);
        }
        print '</div>';
        // if ($field['sortable']) {
        //   print '<span class="re-control dashicons dashicons-randomize at_re_sort_handle"></span></span>';
        // }
        print '<div class="pull-right repeater-remove-btn">';
        print '<button id="remove-btn" class="btn btn-danger" onclick="$(this).parents(\'.items\').remove()">' . __('Remove', 'ewpmb') . '</button></div>';
        print '</div>';
        $c++;
      }
    }
    else {
      // Add new fields
      print '<div class="items" data-group="' . $field['id'] . '">';
      print '<div class="item-content">';
      foreach ($field['fields'] as $repeater_field) {
        if ($repeater_field['type'] != 'wysiwyg') {
          call_user_func([$this, 'show_field_' . $repeater_field['type']], $repeater_field, '');
        }
        else {
          call_user_func([$this, 'show_field_' . $repeater_field['type']], $repeater_field, '', true);
        }
      }
      print '</div>';
      print '<div class="repeater-remove-btn"><button type="button" class="btn btn-danger remove-btn">' . __('Remove', 'ewpmb') . '</button></div>';
      print '</div>';
      print '</div>';
    }
    $this->show_field_end($field);
    print '<script>jQuery(function($){$("#repeater").createRepeater({showFirstItemToDefault:true});});</script>';
  }

  /**
   * Start the field presentation.
   *
   * @since 1.0
   * @access public
   * @param array $field
   */
  public function show_field_begin($field) {
    if ($field['side'] == 1) {
      print '<div class="field-block-flex">';
      if (!empty($field['name'])) {
        print '<div class="block-left label">';
        print '<label for="' . $field['id'] . '">' . $field['name'] . '</label>';
        print '</div>';
      }
      print '<div class="block-right field">';
    }
    else {
      print '<div class="field-block">';
      if (!empty($field['name'])) {
        print '<div class="label">';
        print '<label for="' . $field['id'] . '">' . $field['name'] . '</label>';
        print '<br/>';
      }
    }
  }

  /**
   * End the field presentation.
   *
   * @since 1.0
   * @access public
   * @param array $field
   */
  public function show_field_end($field) {
    // If there's a field description, print it.
    if (isset($field['desc']) && $field['desc'] != '') {
      print '<div class="desc-field">' . $field['desc'] . '</div>';
    }
    print '</div></div>';
  }

  /**
   * Handle the fields classes.
   * 
   * @since 1.0
   * @access public
   * @param array $field
   */
  public function field_classes($field) {
    $type = $field['type'];
    if (empty($field['class'])) {
      return 'class="at-' . $type . '"';
    }
    else {
      return 'class="at-' . $type . ' ' . $field['class'] . '"';
    }
  }

  /**
   * Show text field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_text($field, $data) {
    // Prepare the field attributes.
    $attributes = [
      'id' => 'id="' . $field['id'] . '"',
      'data' => 'data-name="' . $field['id'] . '"',
      'size' => 'size="' . $field['size'] . '"',
      'value' => 'value="' . $data . '"',
      'class' => $this->field_classes($field),
    ];
    // Print the field.
    $this->show_field_begin($field);
    print '<input type="text" ' . implode(' ', $attributes) . '>';
    $this->show_field_end($field);
  }

  /**
   * Show number field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_number($field, $data) {
    // Prepare the field attributes.
    $attributes = [
      'id' => 'id="' . $field['id'] . '"',
      'data' => 'data-name="' . $field['id'] . '"',
      'min' => 'min="' . $field['min'] . '"',
      'max' => 'max="' . $field['max'] . '"',
      'step' => 'step="' . $field['step'] . '"',
      'value' => 'value="' . $data . '"',
      'class' => $this->field_classes($field),
    ];
    // Print the field.
    $this->show_field_begin($field);
    print '<input type="number" ' . implode(' ', $attributes) . '>';
    $this->show_field_end($field);
  }

  /**
   * Show date field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_date($field, $data) {
    $this->show_field_begin($field);
    $id = $field['id'];
    $class = $this->field_classes($field);
    print '<input type="text"' . $class . ' name="' . $id . '" id="' . $id . '" value="' . $data . '" rel="' . $field['format'] . '" />';
    $this->show_field_end($field);
  }

  /**
   * Show textarea field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_textarea($field, $data) {
    $this->show_field_begin($field);
    $id = $field['id'];
    $class = $this->field_classes($field);
    print '<textarea ' . $class . ' name="' . $id . '" id="' . $id . '" cols="60" rows="10">' . $data . '</textarea>';
    $this->show_field_end($field);
  }

  /**
   * Show taxonomy field.
   * 
   * Used to create a category/tags/custom taxonomy checkbox list or a select
   * dropdown.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   *
   * @uses get_terms()
   */
  public function show_field_taxonomy($field, $data) {
    global $post;
    if (!is_array($data)) {
      $data = (array)$data;
    }
    $this->show_field_begin($field);
    $options = $field['options'];
    $terms = get_terms($options['taxonomy'], $options['args']);
    $class = $this->field_classes($field);
    // Checkbox_list
    if ('checkbox_list' == $options['type']) {
      foreach ($terms as $term) {
        print "<input type='checkbox' " . $class . " name='{$field['id']}[]' value='$term->slug'" . checked(in_array($term->slug, $data), true, false) . " /> $term->name<br/>";
      }
    }
    // Select
    else {
      print "<select " . $class . " name='{$field['id']}" . ($field['multiple'] ? "[]' multiple='multiple'" : "'") . ">";
      foreach ($terms as $term) {
        print '<option value="' . $term->slug .'"' . selected(in_array($term->slug, $data), true, false) . '>' . $term->name . '</option>';
      }
      print '</select>';
    }
    $this->show_field_end($field);
  }

  /**
   * Show wysiwig field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_wysiwyg($field, $data, $in_repeater = false) {
    $this->show_field_begin($field);
    if ($in_repeater) {
      print "<textarea class='at-wysiwyg theEditor large-text".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}' id='{$field['id']}' cols='60' rows='10'>{$data}</textarea>";
    }
    else{
      $settings = (isset($field['settings']) && is_array($field['settings']) ? $field['settings'] : array());
      $settings['editor_class'] = 'at-wysiwyg' . (isset($field['class']) ? ' ' . $field['class'] : '');
      $id = str_replace("_", "", $this->stripNumeric(strtolower($field['id'])));
      wp_editor(html_entity_decode($data), $id, $settings);
    }
    $this->show_field_end($field);
  }

  /**
   * Show code editor field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_code($field, $data) {
    $this->show_field_begin($field);
    print "<textarea class='code_text" . (isset($field['class']) ? ' ' . $field['class'] : '' ) . "' name='{$field['id']}' id='{$field['id']}' data-lang='{$field['syntax']}' ".( isset($field['style'])? "style='{$field['style']}'" : '' )." data-theme='{$field['theme']}'>{$data}</textarea>";
    $this->show_field_end($field);
  }

  /**
   * Show hidden field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_hidden($field, $data) {
    print "<input type='hidden' " . (isset($field['style']) ? "style='{$field['style']}' " : '') . " class='at-text" . (isset($field['class']) ? ' ' . $field['class'] : '' )."' name='{$field['id']}' id='{$field['id']}' value='{$data}'/>";
  }

  /**
   * Show paragraph field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   */
  public function show_field_paragraph($field) {
    $this->show_field_begin($field);
    print '<p>' . $field['value'] . '</p>';
    $this->show_field_end($field);
  }

  /**
   * Show select field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_select($field, $data) {
    if (!is_array($data)) {
      $data = (array)$data;
    }
    $this->show_field_begin($field);
    print "<select " . (isset($field['style']) ? "style='{$field['style']}' " : '' ) . " class='at-select" . (isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}" . ( $field['multiple'] ? "[]' id='{$field['id']}' multiple='multiple'" : "'" ) . ">";
    foreach ($field['options'] as $key => $value) {
      print "<option value='{$key}'" . selected(in_array($key, $data), true, false) . ">{$value}</option>";
    }
    print "</select>";
    $this->show_field_end($field);
  }

  /**
   * Show radio field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_radio($field, $data) {
    if (!is_array($data)) {
      $data = (array)$data;
    }
    $this->show_field_begin($field);
      foreach ( $field['options'] as $key => $value ) {
        print "<input type='radio' ".( isset($field['style'])? "style='{$field['style']}' " : '' )." class='at-radio".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}' value='{$key}'" . checked( in_array( $key, $data ), true, false ) . " /> <span class='at-radio-label'>{$value}</span>";
      }
    $this->show_field_end($field);
  }

  /**
   * Show checkbox field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_checkbox($field, $data) {
    $this->show_field_begin($field);
    print "<input type='checkbox' ".( isset($field['style'])? "style='{$field['style']}' " : '' )." class='rw-checkbox".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}' id='{$field['id']}'" . checked(!empty($data), true, false) . " />";
    $this->show_field_end($field);
  }

  /**
   * Show file field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_file($field, $data) {
    wp_enqueue_media();
    $this->show_field_begin($field);
    $std = isset($field['std']) ? $field['std'] : ['id' => '', 'url' => ''];
    $multiple = isset($field['multiple']) ? $field['multiple'] : false;
    $multiple = ($multiple) ? "multiFile '" : "";
    $name = esc_attr($field['id']);
    $value = isset($data['id']) ? $data : $std;
    $has_file = (empty($value['url'])) ? false : true;
    $type = isset($field['mime_type']) ? $field['mime_type'] : '';
    $ext = isset($field['ext']) ? $field['ext'] : '';
    $type = (is_array($type) ? implode("|",$type) : $type);
    $ext = (is_array($ext) ? implode("|",$ext) : $ext);
    $id = $field['id'];
    $li = ($has_file) ? "<li><a href='{$value['url']}' target='_blank'>{$value['url']}</a></li>" : "";
    print "<span class='simplePanelfilePreview'><ul>{$li}</ul></span>";
    print "<input type='hidden' name='{$name}[id]' value='{$value['id']}'/>";
    print "<input type='hidden' name='{$name}[url]' value='{$value['url']}'/>";
    if ($has_file) {
      print '<input type="button" class="' . $multiple . ' button simplePanelfileUploadclear" id="' . $id . '" value="' . __('Remove File', 'ewpmb') . '" data-mime_type="' . $type . '" data-ext="' . $ext . '">';
    }
    else {
      print '<input type="button" class="' . $multiple . ' button simplePanelfileUpload" id="' . $id . '" value="' . __('Upload File', 'ewpmb') . '" data-mime_type="' . $type . '" data-ext="' . $ext . '">';
    }
    $this->show_field_end($field);
  }

  /**
   * Show image field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param array $data
   */
  public function show_field_image($field, $data) {
    wp_enqueue_media();
    $this->show_field_begin($field);
    $std = isset($field['std']) ? $field['std'] : ['id' => '', 'url' => ''];
    $name = esc_attr($field['id']);
    $value = isset($data['id']) ? $data : $std;
    $value['url'] = isset($data['src']) ? $data['src'] : $value['url']; // backwards capability
    $has_image = empty($value['url']) ? false : true;
    $w = isset($field['width']) ? $field['width'] : 'auto';
    $h = isset($field['height']) ? $field['height'] : 'auto';
    $PreviewStyle = "style='width: $w; height: $h;" . ((!$has_image) ? "display: none;'" : "'");
    $id = $field['id'];
    $multiple = isset($field['multiple']) ? $field['multiple'] : false;
    $multiple = ($multiple) ? "multiFile " : "";

    print "<span class='simplePanelImagePreview'><img {$PreviewStyle} src='{$value['url']}'><br/></span>";
    print "<input type='hidden' name='{$name}[id]' value='{$value['id']}'/>";
    print "<input type='hidden' name='{$name}[url]' value='{$value['url']}'/>";
    if ($has_image) {
      print '<input class="' . $multiple . ' button simplePanelimageUploadclear" id="' . $id . '" value="' . __('Remove Image', 'ewpmb') . '" type="button">';
    }
    else {
      print '<input class="' . $multiple . ' button simplePanelimageUpload" id="' . $id . '" value="' . __('Upload Image', 'ewpmb') . '" type="button">';
    }
    $this->show_field_end($field);
  }

  /**
   * Show color field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_color($field, $data) {
    if (empty($data)) {
      $data = '#';
    }
    $this->show_field_begin($field);
    if (wp_style_is('wp-color-picker', 'registered')) { // Iris color picker since 3.5
      print "<input class='at-color-iris".(isset($field['class'])? " {$field['class']}": "")."' type='text' name='{$field['id']}' id='{$field['id']}' value='{$data}' size='8' />";
    }
    else {
      print "<input class='at-color".(isset($field['class'])? " {$field['class']}": "")."' type='text' name='{$field['id']}' id='{$field['id']}' value='{$data}' size='8' />";
      print "<input type='button' class='at-color-select button' rel='{$field['id']}' value='" . __( 'Select a color', 'ewpmb') . "'/>";
      print "<div style='display:none' class='at-color-picker' rel='{$field['id']}'></div>";
    }
    $this->show_field_end($field);
  }

  /**
   * Show checkbox list field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_checkbox_list($field, $data) {
    if (!is_array($data)) {
      $data = (array)$data;
    }
    $this->show_field_begin($field);
    $html = array();
    foreach ($field['options'] as $key => $value) {
      $html[] = "<input type='checkbox' ".( isset($field['style'])? "style='{$field['style']}' " : '' )."  class='at-checkbox_list".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}[]' value='{$key}'" . checked( in_array( $key, $data ), true, false ) . " /> {$value}";
    }
    print implode('<br />', $html);
    $this->show_field_end($field);
  }

  /**
   * Show time field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_time($field, $data) {
    $this->show_field_begin($field);
    $ampm = ($field['ampm']) ? 'true' : 'false';
    print "<input type='text'  ".( isset($field['style'])? "style='{$field['style']}' " : '' )." class='at-time".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}' id='{$field['id']}' data-ampm='{$ampm}' rel='{$field['format']}' value='{$data}' size='30' />";
    $this->show_field_end($field);
  }

  /**
   * Show posts field.
   *
   * Used to checkbox list or select dropdown.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_posts($field, $data) {
    global $post;
    if (!is_array($data)) $data = (array)$data;
    $this->show_field_begin($field);
    $options = $field['options'];
    $posts = get_posts($options['args']);
    // Checkbox_list
    if ('checkbox_list' == $options['type']) {
      foreach ($posts as $p) {
        print "<input type='checkbox' " . (isset($field['style'])? "style='{$field['style']}' " : '' )." class='at-posts-checkbox".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}[]' value='$p->ID'" . checked(in_array($p->ID, $data), true, false) . " /> $p->post_title<br/>";
      }
    }
    // Select
    else {
      print "<select ".( isset($field['style'])? "style='{$field['style']}' " : '' )." class='at-posts-select".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}" . ($field['multiple'] ? "[]' multiple='multiple' style='height:auto'" : "'") . ">";
      if (isset($field['emptylabel'])) {
        print '<option value="-1">'.(isset($field['emptylabel'])? $field['emptylabel']: __('Select...', 'ewpmb')).'</option>';
      }
      foreach ($posts as $p) {
        print "<option value='$p->ID'" . selected(in_array($p->ID, $data), true, false) . ">$p->post_title</option>";
      }
      print "</select>";
    }
    $this->show_field_end($field);
  }

  /**
   * Show conditional checkbox field.
   *
   * @since 1.0
   * @access public
   * @param array $field
   * @param string $data
   */
  public function show_field_cond($field, $data) {
    global $post;
    $this->show_field_begin($field);
    $checked = false;
    if (is_array($data) && isset($data['enabled']) && $data['enabled'] == 'on') {
      $checked = true;
    }
    print "<input type='checkbox' class='conditinal_control' name='{$field['id']}[enabled]' id='{$field['id']}'" . checked($checked, true, false) . " />";
    // Start showing the fields.
    $display = ($checked)? '' :  ' style="display: none;"';
    print '<div class="conditinal_container"'.$display.'><table>';
    foreach ((array)$field['fields'] as $f) {
      // Reset var $id for cond
      $id = '';
      $id = $field['id'].'['.$f['id'].']';
      $m = '';
      $m = (isset($data[$f['id']])) ? $data[$f['id']]: '';
      $m = ( $m !== '' ) ? $m : (isset($f['std'])? $f['std'] : '');
      if ('image' != $f['type'] && $f['type'] != 'repeater') {
        $m = is_array($m) ? array_map('esc_attr', $m) : esc_attr($m);
      }
      elseif ('image' == $f['type']) {
        $saved_data = get_post_meta($post->ID, $field['id']);
        if (is_array($saved_data[0][$f['id']])) {
          $m = $saved_data[0][$f['id']];
        }
      }
      // Set the new id field in an array format.
      $f['id'] = $id;
      print '<tr>';
      call_user_func([$this, 'show_field_' . $f['type']], $f, $m);
      print '</tr>';
    }
    print '</table></div>';
    $this->show_field_end($field);
  }

  /**
   * Save meta box data.
   *
   * @since 1.0
   * @access public
   * @param string $post_id
   */
  public function save($post_id) {
    global $post_type;
    $post_type_object = get_post_type_object($post_type);
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) // Check Autosave
    || (!isset($_POST['post_ID']) || $post_id != $_POST['post_ID']) // Check Revision
    || (!in_array($post_type, $this->_meta_box['pages'])) // Check if current post type is supported.
    || (!check_admin_referer(basename(__FILE__), 'easy_meta_box_nonce')) // Check nonce - Security
    || (!current_user_can($post_type_object->cap->edit_post, $post_id))) // Check permission
    {
      return $post_id;
    }

    foreach ($this->_fields as $field) {
      $name = $field['id'];
      $type = $field['type'];
      $old = get_post_meta($post_id, $name, !$field['multiple']);
      $new = (isset($_POST[$name])) ? $_POST[$name] : (($field['multiple']) ? [] : '');
      // Validate meta value
      if (class_exists('at_Meta_Box_Validate') && method_exists('at_Meta_Box_Validate', $field['validate_func'])) {
        $new = call_user_func(['at_Meta_Box_Validate', $field['validate_func']], $new);
      }

      // Skip on Paragraph field
      if ($type != 'paragraph') {
        // Call defined method to save meta value, if there's no methods, call common one.
        $save_func = 'save_field_' . $type;
        if (method_exists($this, $save_func)) {
          call_user_func([$this, 'save_field_' . $type], $post_id, $field, $old, $new);
        }
        else {
          $this->save_field($post_id, $field, $old, $new);
        }
      }
    }
  }

  /**
   * Common function to save fields.
   *
   * @since 1.0
   * @access public
   * @param string $post_id
   * @param array $field
   * @param array $old
   * @param array|mixed $new
   */
  public function save_field($post_id, $field, $old, $new) {
    $name = $field['id'];
    delete_post_meta($post_id, $name);
    if ($new === '' || $new === []) {
      return;
    }
    if ($field['multiple']) {
      foreach ($new as $add_new) {
        add_post_meta($post_id, $name, $add_new, false);
      }
    }
    else {
      update_post_meta($post_id, $name, $new);
    }
  }

  /**
   * Save image field.
   *
   * @since 1.0
   * @access public
   * @param string $post_id
   * @param array $field
   * @param array $old
   * @param array|mixed $new
   */
  public function save_field_image($post_id, $field, $old, $new) {
    $name = $field['id'];
    delete_post_meta($post_id, $name);
    if ($new === '' || $new === [] || $new['id'] == '' || $new['url'] == '') {
      return;
    }
    update_post_meta($post_id, $name, $new);
  }

  /**
   * Save Wysiwyg field.
   *
   * @since 1.0
   * @access public
   * @param string $post_id
   * @param array $field
   * @param array $old
   * @param array $new
   */
  public function save_field_wysiwyg($post_id, $field, $old, $new) {
    $id = str_replace('_', '', $this->stripNumeric(strtolower($field['id'])));
    $new = (isset($_POST[$id])) ? $_POST[$id] : (($field['multiple']) ? [] : '');
    $this->save_field($post_id, $field, $old, $new);
  }

  /**
   * Save repeater Fields.
   *
   * @since 1.0
   * @access public
   * @param string $post_id
   * @param array $field
   * @param string|mixed $old
   * @param string|mixed $new
   */
  public function save_field_repeater($post_id, $field, $old, $new) {
    if (is_array($new) && count($new) > 0) {
      foreach ($new as $n) {
        foreach ($field['fields'] as $f) {
          $type = $f['type'];
          switch($type) {
            case 'wysiwyg':
              $n[$f['id']] = wpautop($n[$f['id']]);
              break;
            default:
              break;
          }
        }
        if (!$this->is_array_empty($n)) {
          $temp[] = $n;
        }
      }
      if (isset($temp) && count($temp) > 0 && !$this->is_array_empty($temp)) {
        update_post_meta($post_id, $field['id'], $temp);
      }
      else {
        // Remove old meta if exist.
        delete_post_meta($post_id, $field['id']);
      }
    }
    else {
      // Remove old meta if exist.
      delete_post_meta($post_id, $field['id']);
    }
  }

  /**
   * Save File Field.
   *
   * @since 1.0
   * @access public
   * @param string $post_id
   * @param array $field
   * @param array $old
   * @param array $new
   */
  public function save_field_file($post_id, $field, $old, $new) {
    $name = $field['id'];
    delete_post_meta($post_id, $name);
    if ($new === '' || $new === [] || $new['id'] == '' || $new['url'] == '') {
      return;
    }
    update_post_meta($post_id, $name, $new);
  }

  /**
   * Add missing/default values for meta box and fields.
   *
   * @since 1.0
   * @access public
   */
  public function add_missed_values() {
    // Default values for meta boxes.
    $this->_meta_box = array_merge([
      'context' => 'normal',
      'priority' => 'high',
      'pages' => ['post'],
      'prefix' => '',
      'local_images' => false,
      'use_with_theme' => false,
    ], (array)$this->_meta_box);

    // Default values for fields.
    foreach ($this->_fields as &$field) {
      $multiple = in_array($field['type'], ['checkbox_list', 'file', 'image']);
      $std = $multiple ? [] : '';
      $format = 'date' == $field['type'] ? 'dd/mm/yyyy' : ('time' == $field['type'] ? 'HH:mm' : '');
      $field = array_merge([
        'multiple' => $multiple,
        'std' => $std,
        'desc' => '',
        'format' => $format,
        'validate_func' => '',
      ], $field);
    }
  }

  /**
   * Check if field with $type exists.
   *
   * @since 1.0
   * @access public
   * @param string $type
   */
   public function has_field($type) {
    // Faster search in single dimension array.
    if (count($this->field_types) > 0) {
      return in_array($type, $this->field_types);
    }

    // Run once over all fields and store the types in a local array.
    $temp = [];
    foreach ($this->_fields as $field) {
      $temp[] = $field['type'];
      if ('repeater' == $field['type'] || 'cond' == $field['type']) {
        foreach ((array)$field['fields'] as $repeater_field) {
          $temp[] = $repeater_field['type'];
        }
      }
    }

    // Remove duplicates.
    $this->field_types = array_unique($temp);
    // Call this function one more time to have an array of field types.
    return $this->has_field($type);
  }

  /**
   * Check if current page is edit page.
   *
   * @since 1.0
   * @access public
   */
  public function is_edit_page() {
    global $pagenow;
    return in_array($pagenow, ['post.php', 'post-new.php']);
  }

  /**
   * Fixes the odd indexing of multiple file uploads.
   *
   * Goes from the format:
   * $_FILES['field']['key']['index']
   * to
   * The More standard and appropriate:
   * $_FILES['field']['index']['key']
   *
   * @since 1.0
   * @access public
   * @param string $files
   */
  public function fix_file_array(&$files) {
    $output = [];
    foreach ($files as $key => $list) {
      foreach ($list as $index => $value) {
        $output[$index][$key] = $value;
      }
    }
    return $output;
  }

  /**
   * Get proper JQuery UI version.
   *
   * Used in order to not conflict with WP Admin Scripts.
   *
   * @since 1.0
   * @access public
   */
  public function get_jqueryui_ver() {
    global $wp_version;
    if (version_compare($wp_version, '3.1', '>=')) {
      return '1.8.10';
    }
    return '1.7.3';
  }

  /**
   * Add field (generic function).
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $args
   */
  public function addField($id, $args) {
    $new_field = [
      'id' => $id,
      'std' => '',
      'desc' => '',
      'side' => 0,
      'class' => '',
    ];
    $new_field = array_merge($new_field, $args);
    $this->_fields[] = $new_field;
  }

  /**
   * Add text field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $args
   *   'name' => The label title, optional string
   *   'size' => The wide field number of characters, optional string, default 30
   *   'desc' => The description behind the field, optional string
   *   'std' => The default value, optional string
   *   'side' => If the field is displayed side by side with the title, optional integer/string
   *   'class' => Classes names to be added on the field, optional string
   *   'validate_func' => Validate function, optional string
   * @param bool $repeater Is this a field inside a repeater?
   */
  public function addText($id, $args, $repeater = false) {
    $new_field = [
      'id'=> $id,
      'type' => 'text',
      'name' => __('Text Field', 'ewpmb'),
      'size' => '60',
      'desc' => '',
      'std' => '',
      'side' => 0,
      'class' => '',
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   * Add number field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $args
   *   'name' => The label title, optional string
   *   'size' => The wide field number of characters, optional string, default 30
   *   'desc' => The description behind the field, optional string
   *   'std' => The default value, optional string
   *   'min' => The minimum value, optional integer/string
   *   'max' => The maximum value, optional integer/string
   *   'step' => The step value, optional integer/string
   *   'side' => If the field is displayed side by side with the title, optional integer/string
   *   'class' => Classes names to be added on the field, optional string
   *   'validate_func' => Validate function, optional string
   * @param bool $repeater Is this a field inside a repeater?
   */
  public function addNumber($id, $args, $repeater = false) {
    $new_field = [
      'id' => $id,
      'type' => 'number',
      'name' => __('Number Field', 'ewpmb'),
      'desc' => '',
      'std' => 0,
      'min' => 0,
      'max' => '',
      'step' => 1,
      'side' => 0,
      'class' => '',
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   * Add date field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $args
   *   'name' => The label title, optional string
   *   'desc' => The description behind the field, optional string
   *   'std' => The default value, optional string
   *   'min' => The minimum date value, optional date formatted string
   *   'max' => The maximum date value, optional date formatted string
   *   'format' => The date format, optional. Default "dd/mm/yy".
   *   'step' => The step value, optional integer/string
   *   'side' => If the field is displayed side by side with the title, optional integer/string
   *   'class' => Classes names to be added on the field, optional string
   *   'validate_func' => Validate function, optional string
   * @param bool $repeater Is this a field inside a repeater?
   * 
   * @see Date formats: http://goo.gl/Wcwxn
   */
  public function addDate($id, $args, $repeater = false) {
    $new_field = [
      'id' => $id,
      'type' => 'date',
      'name' => __('Date Field', 'ewpmb'),
      'desc' => '',
      'format' => 'dd/mm/yy',
      'std' => '',
      'min' => '',
      'max' => '',
      'step' => '',
      'side' => 0,
      'class' => '',
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   * Add textarea field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $args
   *   'name' => The label title, optional string
   *   'desc' => The description behind the field, optional string
   *   'std' => The default value, optional string
   *   'rows' => The number of rows in the box, optional integer/string. Default 10
   *   'cols' => The number of columns in the box, optional integer/string. Default 60
   *   'side' => If the field is displayed side by side with the title, optional integer/string
   *   'class' => Classes names to be added on the field, optional string
   *   'validate_func' => Validate function, optional string
   * @param bool $repeater Is this a field inside a repeater?
   */
  public function addTextarea($id, $args, $repeater = false) {
    $new_field = [
      'id' => $id,
      'type' => 'textarea',
      'name' => __('Textarea Field', 'ewpmb'),
      'desc' => '',
      'std' => '',
      'rows' => '',
      'cols' => '',
      'side' => 0,
      'class' => '',
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   * Add select field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param array $options Value pairs for options
   * @param mixed|array $args
   *   'name' => The label title, optional string
   *   'desc' => The description behind the field, optional string
   *   'std' => The default value, optional string
   *   'multiple' => Select multiple values, optional boolean. Default false.
   *   'size' => The number of values to be displayed in a multiple selection element. Optional integer/string. Default 1
   *   'side' => If the field is displayed side by side with the title, optional integer/string
   *   'class' => Classes names to be added on the field, optional string
   *   'validate_func' => Validate function, optional string
   * @param bool $repeater Is this a field inside a repeater?
   */
  public function addSelect($id, $options, $args, $repeater = false) {
    $new_field = [
      'id' => $id,
      'type' => 'select',
      'name' => __('Select Field', 'ewpmb'),
      'desc' => '',
      'std' => [],
      'multiple' => false,
      'size' => 1,
      'options' => $options,
      'side' => 0,
      'class' => '',
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   * Add radio field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param array $options Value pairs for options
   * @param mixed|array $args
   *   'name' => The label title, optional string
   *   'desc' => The description behind the field, optional string
   *   'std' => The default value, optional string
   *   'side' => If the field is displayed side by side with the title, optional integer/string
   *   'class' => Classes names to be added on the field, optional string
   *   'validate_func' => Validate function, optional string
   * @param bool $repeater Is this a field inside a repeater?
   */
  public function addRadio($id, $options, $args, $repeater = false) {
    $new_field = [
      'id' => $id,
      'type' => 'radio',
      'name' => __('Radio Field', 'ewpmb'),
      'std' => [],
      'desc' => '',
      'options' => $options,
      'side' => 0,
      'class' => '',
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   * Add image field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $args
   *   'name' => The label title, optional string
   *   'desc' => The description behind the field, optional string
   *   'side' => If the field is displayed side by side with the title, optional integer/string
   *   'class' => Classes names to be added on the field, optional string
   *   'validate_func' => Validate function, optional string
   * @param bool $repeater Is this a field inside a repeater?
   */
  public function addImage($id, $args, $repeater = false) {
    $new_field = [
      'id' => $id,
      'type' => 'image',
      'name' => __('Image Field', 'ewpmb'),
      'desc' => '',
      'std' => ['id' => '', 'url' => ''],
      'multiple' => false,
      'side' => 0,
      'class' => '',
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   * Add WYSIWYG Field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $args
   *   'name' => The label title, optional string
   *   'desc' => The description behind the field, optional string
   *   'side' => If the field is displayed side by side with the title, optional integer/string
   *   'class' => Classes names to be added on the field, optional string
   *   'validate_func' => Validate function, optional string
   * @param bool $repeater Is this a field inside a repeater?
*/
  public function addWysiwyg($id, $args, $repeater = false) {
    $new_field = [
      'id' => $id,
      'type' => 'wysiwyg',
      'name' => __('WYSIWYG Editor Field', 'ewpmb'),
      'std' => '',
      'desc' => '',
      'side' => 0,
      'class' => '',
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   * Add taxonomy field.
   * 
   * @author Paulino Michelazzo
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $options Taxonomy fields options
   *   'taxonomy' => The taxonomy name linked to. Default is category.
   *   'type' => How to display the taxonomy? 'select' (default) or 'checkbox_list'
   *   'args' => Arguments to query the taxonomy, Default: 'hide_empty' => 0
   * @param mixed|array $args
   *   'name' => The label title, optional string
   *   'desc' => The description behind the field, optional string
   *   'side' => If the field is displayed side by side with the title, optional integer/string
   *   'class' => Classes names to be added on the field, optional string
   * @param bool $repeater Is this a field inside a repeater?
   * 
   * @see Taxonomy arguments: http://goo.gl/uAANN
   */
  public function addTaxonomy($id, $options, $args, $repeater = false) {
    $temp = [
      'tax' => 'category',
      'type' => 'select',
      'args' => ['hide_empty' => 0],
    ];
    $options = array_merge($temp, $options);
    $new_field = [
      'id' => $id,
      'type' => 'taxonomy',
      'name' => __('Taxonomy Field', 'ewpmb'),
      'desc' => '',
      'options'=> $options,
      'side' => 0,
      'class' => '',
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   * Add repeater field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $args
   *   'name' => The label title, optional string
   *   'desc' => The description behind the field, optional string
   *   'fields' => Fields to repeater
   *   'side' => If the field is displayed side by side with the title, optional integer/string
   *   'class' => Classes names to be added on the field, optional string
   *   'validate_func' => Validate function, optional string
   */
  public function addRepeaterBlock($id, $args) {
    $new_field = [
      'id' => $id,
      'type' => 'repeater',
      'name' => __('Repeater Field', 'ewpmb'),
      'fields' => [],
      'inline' => false,
      'sortable' => true,
      'side' => 0,
      'class' => '',
    ];
    $new_field = array_merge($new_field, $args);
    $this->_fields[] = $new_field;
  }

  /**
   * Add time field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $args
   *   'name' => field name/label string optional
   *   'desc' => field description, string optional
   *   'std' => default value, string optional
   *   'validate_func' => validate function, string optional
   *   'format' => time format, default HH:mm. Optional.
   * @param bool $repeater Is this a field inside a repeater?
   * 
   * @see Time formats: http://goo.gl/83woX
   */
  public function addTime($id, $args, $repeater = false) {
    $new_field = [
      'type' => 'time',
      'id' => $id,
      'std' => '',
      'desc' => '',
      'format' => 'HH:mm',
      'name' => __('Time Field', 'ewpmb'),
      'ampm' => false,
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   *  Add code editor field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $args
   *   'name' => field name/label string optional
   *   'desc' => field description, string optional
   *   'std' => default value, string optional
   *   'style' => custom style for field, string optional
   *   'syntax' => syntax language to use in editor (php,javascript,css,html)
   *   'validate_func' => validate function, string optional
   * @param bool $repeater Is this a field inside a repeater?
   */
  public function addCode($id, $args, $repeater = false) {
    $new_field = [
      'type' => 'code',
      'id' => $id,
      'std' => '',
      'desc' => '',
      'style' => '',
      'name' => __('Code Editor Field', 'ewpmb'),
      'syntax' => 'php',
      'theme' => 'default',
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   * Add hidden field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $args
   *   'name' => field name/label string optional
   *   'desc' => field description, string optional
   *   'std' => default value, string optional
   *   'style' => custom style for field, string optional
   *   'validate_func' => validate function, string optional
   * @param bool $repeater Is this a field inside a repeater?
   */
  public function addHidden($id, $args, $repeater = false) {
    $new_field = [
      'type' => 'hidden',
      'id' => $id,
      'std' => '',
      'desc' => '',
      'style' =>'',
      'name' => __('Text Field', 'ewpmb'),
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   * Add paragraph field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $args
   * @param bool $repeater Is this a field inside a repeater?
   */
  public function addParagraph($id, $args, $repeater = false) {
    $new_field = [
      'type' => 'paragraph',
      'id' => $id,
      'value' => ''
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   *  Add checkbox field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $args
   *   'name' => field name/label string optional
   *   'desc' => field description, string optional
   *   'std' => default value, string optional
   *   'validate_func' => validate function, string optional
   * @param bool $repeater Is this a field inside a repeater?
   */
  public function addCheckbox($id, $args, $repeater = false) {
    $new_field = [
      'type' => 'checkbox',
      'id' => $id,
      'std' => '',
      'desc' => '',
      'style' => '',
      'name' => __('Checkbox Field', 'ewpmb'),
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   *  Add checkboxList field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param array $options Value pairs for select options.
   * @param mixed|array $args
   *   'name' => field name/label string optional
   *   'desc' => field description, string optional
   *   'std' => default value, string optional
   *   'validate_func' => validate function, string optional
   * @param bool $repeater Is this a field inside a repeater?
   *
   * @return remember to call: $checkbox_list = get_post_meta(get_the_ID(), 'meta_name', false);
   *   which means the last param as false to get the values in an array.
   */
  public function addCheckboxList($id, $options, $args, $repeater = false) {
    $new_field = [
      'type' => 'checkbox_list',
      'id' => $id,
      'std' => '',
      'desc' => '',
      'style' => '',
      'name' => __('Checkbox List Field', 'ewpmb'),
      'options' => $options,
      'multiple' => true,
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   *  Add color field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $args
   *   'name' => field name/label string optional
   *   'desc' => field description, string optional
   *   'std' => default value, string optional
   *   'validate_func' => validate function, string optional
   * @param bool $repeater Is this a field inside a repeater?
   */
  public function addColor($id, $args, $repeater = false) {
    $new_field = [
      'type' => 'color',
      'id' => $id,
      'std' => '',
      'desc' => '',
      'name' => __('ColorPicker Field', 'ewpmb'),
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   *  Add file field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $args
   *   'name' => field name/label string optional
   *   'desc' => field description, string optional
   *   'validate_func' => validate function, string optional
   * @param bool $repeater Is this a field inside a repeater?
   */
  public function addFile($id, $args, $repeater = false) {
    $new_field = [
      'type' => 'file',
      'id' => $id,
      'desc' => '',
      'name' => __('File Field', 'ewpmb'),
      'multiple' => false,
      'std' => ['id' => '', 'url' => ''],
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   * Add posts field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $options Taxonomy field options.
   *   'post_type' => post type name, 'post' (default) 'page' or any custom post type
   *   'type' => how to show posts? 'select' (default) or 'checkbox_list'
   *   'args' => arguments to query posts, see http://goo.gl/is0yK default ('posts_per_page' => -1)
   * @param mixed|array $args
   *   'name' => field name/label string optional
   *   'desc' => field description, string optional
   *   'std' => default value, string optional
   *   'validate_func' => validate function, string optional
   * @param bool $repeater Is this a field inside a repeater?
   */
  public function addPosts($id, $options, $args, $repeater = false) {
    $post_type = isset($options['post_type']) ? $options['post_type'] : (isset($args['post_type']) ? $args['post_type'] : 'post');
    $type = isset($options['type']) ? $options['type'] : 'select';
    $q = ['posts_per_page' => -1, 'post_type' => $post_type];
    if (isset($options['args'])) {
      $q = array_merge($q, (array)$options['args']);
    }
    $options = ['post_type' => $post_type, 'type' => $type, 'args' => $q];
    $new_field = [
      'type' => 'posts',
      'id' => $id,
      'desc' => '',
      'name' => __('Posts Field', 'ewpmb'),
      'options'=> $options,
      'multiple' => false,
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   *  Add checkbox conditional field.
   * 
   * @since 1.0
   * @access public
   * @param string $id
   * @param mixed|array $args
   *   'name' => field name/label string optional
   *   'desc' => field description, string optional
   *   'std' => default value, string optional
   *   'validate_func' => validate function, string optional
   *   'fields' => list of fields to show conditionally.
   * @param bool $repeater Is this a field inside a repeater?
   */
  public function addCondition($id, $args, $repeater = false) {
    $new_field = [
      'type' => 'cond',
      'id' => $id,
      'std' => '',
      'desc' => '',
      'style' =>'',
      'name' => __('Conditional Field', 'ewpmb'),
      'fields' => array()
    ];
    $new_field = array_merge($new_field, $args);
    if (false === $repeater) {
      $this->_fields[] = $new_field;
    }
    else {
      return $new_field;
    }
  }

  /**
   * Finish meta box declaration.
   *
   * @since 1.0
   * @access public
   */
  public function Finish() {
    $this->add_missed_values();
  }

  /**
   * Helper to check empty arrays.
   *
   * @since 1.0
   * @access public
   * @param mixed|array $array
   */
  public function is_array_empty($array){
    if (!is_array($array)) {
      return true;
    }
    foreach ($array as $a) {
      if (is_array($a)) {
        foreach ($a as $sub_a) {
          if (!empty($sub_a) && $sub_a != '') {
            return false;
          }
        }
      }
      else {
        if (!empty($a) && $a != '') {
          return false;
        }
      }
    }
    return true;
  }

  /**
   * Validate the upload file type.
   *
   * Check if the uploaded file is of the expected format.
   *
   * @since 1.0
   * @access public
   * @param array $file Uploaded file.
   * 
   * @return array file with error on mismatch.
   * 
   * @uses get_allowed_mime_types() to check allowed types
   */
  function Validate_upload_file_type($file) {
    if (isset($_POST['uploadeType']) && !empty($_POST['uploadeType']) && isset($_POST['uploadeType']) && $_POST['uploadeType'] == 'my_meta_box'){
      $allowed = explode("|", $_POST['uploadeType']);
      $ext =  substr(strrchr($file['name'],'.'),1);

      if (!in_array($ext, (array)$allowed)){
        $file['error'] = __('Sorry, you cannot upload this file type for this field.', 'ewpmb');
        return $file;
      }

      foreach (get_allowed_mime_types() as $key => $value) {
        if (strpos($key, $ext) || $key == $ext)
          return $file;
      }
      $file['error'] = __('Sorry, you cannot upload this file type for this field.', 'ewpmb');
    }
    return $file;
  }

  /**
   * Sanitize the field id.
   *
   * @since 1.0
   * @access public
   * @param string $str String to sanitize.
   * 
   * @return string Sanitized string.
   */
  public function idfy($str){
    return str_replace(" ", "_", $str);
  }

  /**
   * stripNumeric Strip numbers form string.
   *
   * @since 1.0
   * @access public
   * @param string $str
   * 
   * @return string number less string
   */
  public function stripNumeric($str){
    return trim(str_replace(range(0,9), '', $str));
  }

  /**
   * Load the textdomain.
   * 
   * @since 1.0
   * @access public
   * 
   * @return void
   */
  public function load_textdomain(){
    load_textdomain('ewpmb', dirname(__FILE__) . '/locale/' . get_locale() . '.mo');
  }
}
endif;
