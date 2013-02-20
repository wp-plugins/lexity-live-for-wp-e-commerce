<?php
/**
 *
 */
class Sidecar_Field {

  /**
   * @var Sidecar_Plugin_Base
   */
  var $plugin;

  /**
   * @var Sidecar_Form
   */
  var $form;

  /**
   * @var array
   */
  var $section;

  /**
   * @var string
   */
  var $field_name;

  /**
   * @var string
   */
  var $field_label;

  /**
   * @var string
   */
  var $field_type;

  /**
   * @var string
   */
  var $field_help;

  /**
   * @var int
   */
  var $field_size;

  /**
   * @var int|array
   */
  var $field_validator;

  /**
   * @var string
   */
  var $field_default;

  /**
   * @var array
   */
  var $field_options;

  /**
   * @var bool
   */
  var $field_required = false;

  /**
   * @var bool|callable
   */
  var $field_handler = false;

  /**
   * Indicates this field value if used for an API.
   * Defaults to $this->field_name, can be see to another name
   * (i.e. field_name = 'content_type', api_var = 'type'
   * but if set to false it will cause the API to ignore this field.
   * @var null|bool|string
   */
  var $api_var;

  /**
   * @param string $field_name
   * @param array $args
   */
  function __construct( $field_name, $args = array() ) {
    $this->field_name = $field_name;
    /**
     * Copy properties in from $args, if they exist.
     */
    foreach( $args as $property => $value ) {
      if ( property_exists( $this, $property ) ) {
        $this->$property = $value;
      } else if ( property_exists( $this, $property = "field_{$property}" ) ) {
        $this->$property = $value;
      }
    }
    if ( ! $this->field_type )
      $this->field_type = 'password' == $this->field_name ? 'password' : 'text';

    if ( 'hidden' == $this->field_type )
      $this->field_label = false;
    else if ( ! $this->field_label )
      $this->field_label = ucwords( $this->field_name );

    if ( ! $this->field_size )
      $this->field_size = preg_match( '#(text|password)#', $this->field_type ) ? 40 : false;

    if ( is_null( $this->api_var ) )
      $this->api_var = $this->field_name;
  }

  /**
   * @return string
   */
  function get_html() {
    /**
     * @todo: Get options with all expected elements initialized
     */
    $form = $this->form;
    $settings = $form->get_settings();
    $value = isset( $settings[$this->field_name] ) ? esc_attr( $settings[$this->field_name] ) : false;
    $input_name = "{$this->plugin->option_name}[{$form->settings_key}][{$this->field_name}]";
    /**
     * Sets HTML id like the following:
     * @example
     *  HTML name = my_plugin_settings[_form-name}[field-name]
     *  HTML id =>  my-plugin-settings--form-name-field-name
     */
    $input_id = str_replace( array( '[_', '_', '][', '[', ']' ), array( '--', '-', '-', '-', '' ), $input_name );
    $size_html = $this->field_size ? " size=\"{$this->field_size}\"" : '';
    $css_base = $this->plugin->css_base;
    $help_html = $this->field_help ? "\n<br />\n<span class=\"{$css_base}-field-help\">{$this->field_help}</span>" : false;

    if ( 'radio' == $this->field_type ) {
      $html = array( "<ul id=\"{$input_id}-radio-field-options\" class=\"radio-field-options\">" );
      $this_value = $settings[$this->field_name];
      foreach( $this->field_options as $value => $label ) {
        $checked = ( ! empty( $this_value ) && $value == $this_value ) ? 'checked="checked" ' : '';
        $value = esc_attr( $value );
        $html[] =<<<HTML
<li><input type="radio" id="{$input_id}" class="{$css_base}-field" name="{$input_name}" value="{$value}" {$checked}/>
<label for={$input_id}">{$label}</label></li>
HTML;
      }
      $html = implode( "\n", $html ) . "</ul>{$help_html}";
    } else if ( 'hidden' == $this->field_type ) {
      $html =<<<HTML
<input type="hidden" id="{$input_id}" name="{$input_name}" value="{$value}" />
HTML;
    } else {
      $html =<<<HTML
<input type="{$this->field_type}" id="{$input_id}" name="{$input_name}" value="{$value}" class="{$css_base}-field"{$size_html}/>{$help_html}
HTML;
  }
    return $html;
  }
}
