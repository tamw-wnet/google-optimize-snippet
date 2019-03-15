<?php
/*
Plugin Name: Google Optimize Snippet
Description: Sets up the Google Optimize Snippet and provides a checkbox to add it on any page or post.
Version: 1.1
Author: Will Tam
*/

class IEG_Google_Optimize_Snippet {
  private $google_optimize_id;
  private $google_analytics_id;
  private $google_optimize_on_all_pages;
  private $google_analytics_custom_field_defs;

  public function __construct() {
    $this->google_optimize_id = get_option( 'google-optimize-id' ) ? get_option( 'google-optimize-id' ) : '';
    $this->google_analytics_id = get_option( 'google-optimize-analytics-id' ) ? get_option( 'google-optimize-analytics-id' ) : '';
    $this->google_optimize_on_all_pages = (get_option( 'google-optimize-on-all-pages' ) == "enable") ? TRUE : FALSE;
    
    $this->google_analytics_custom_field_defs = array(
      'allowLinker' => 'boolean',
      'cookieDomain' => 'string',
      'cookieName' => 'string',
      'cookiePath' => 'string',
      'sampleRate' => 'integer',
      'siteSpeedSampleRate' => 'integer',
      'alwaysSendReferrer' => 'boolean',
      'allowAnchor' => 'boolean',
      'cookieExpires' => 'integer',
      'legacyCookieDomain' => 'string',
      'legacyHistoryImport' => 'boolean',
      'storeGac' => 'boolean' );


	  add_action('admin_menu', array( $this, 'google_optimize_admin_menu') );


    if ($this->google_optimize_id) {
      add_action('wp_head', array($this, 'add_optimize_snippet_to_head'), 1);
      if ($this->google_optimize_on_all_pages == FALSE) {
        // only bother showing these if we aren't doing the snippet on all pages
        add_action('add_meta_boxes', array($this, 'meta_box_setup'));
        add_action( 'save_post', array( $this, 'meta_box_save' ) );
      }
    }
  }

  function google_optimize_admin_menu(){
	  $page = add_options_page('Google Optimize Settings', 'Google Optimize Settings', 'manage_options', 'google-optimize-settings', array( $this, 'google_optimize_admin') );
  	add_action( 'admin_init', array( $this, 'register_gtm_settings' ) );
  }

  function google_optimize_admin(){ 
    ?>
	  <br /><h1>Google Optimize Settings</h1>
  	<br />
	  <form method="post" action="options.php">
  	<?php settings_fields( 'google-optimize-settings-group' );
	  do_settings_sections( 'google-optimize-settings-group' );?>

    <p>This plugin does the work of installing the necessary Google Optimize 'snippets' -- both the 'page-hiding' snippet and the Optimize container snippet -- in the correct place on the pages you want to run Google Optimize Experiments, but without having to directly edit your theme files.  This plugin is based on the Optimize documentation <a href="https://support.google.com/optimize/answer/7359264" target=_new>here</a> and assumes that you DO have Google Tag Manager (GTM)  installed on your website; but you shouldn't need to make any changes to your GTM setup.</p>
    <p>Running Google Optimize Experiments requires both your Google Analytics Property ID -- that you normally would just deploy via Google Tag Manager -- and also a special Google Optimize Container ID to be setup.  Visit <a href="https://www.google.com/analytics/optimize/" target =_new>Google Optimize</a> for more details.</p>
    <p>Enter the Google Optimize container id for this blog <span class="description">(will start with GTM-something, but will NOT be the same value as the Google Tag Manager id)</span>:<br />
    <input type="text" name="google-optimize-id" value="<?php echo $this->google_optimize_id; ?>"></p>   
    <p>Enter the Google Analytics Property id for this blog <span class="description">(will start with UA-something.  You will NOT remove the Google Analytics Property ID tag from your GTM setup)</span>:<br />
    <input type="text" name="google-optimize-analytics-id" value="<?php echo $this->google_analytics_id; ?>"></p>

    <h3>Running Optimize Experiments</h3>

    <p><b>To enable Google Optimize Experiments on individual pages</b>, you'll need to fill in both fields above AND go into the individual pages in the WordPress editor view to check the 'Add Google Optimize Code' box on those pages.  This will result in both the 'page-hiding' snippet and the Google Optimize container snippet appearing on those pages.</p>

    <?php $optimize_on_home = (get_option( 'google-optimize-on-home' ) == "enable") ? TRUE : FALSE; ?>

    <p><b>To enable Google Optimize Experiments on the front/home page</b>,  check this box: <input type="checkbox" name="google-optimize-on-home" value="enable" <?php echo ($optimize_on_home ? "checked" : ""); ?> /> The home/front page doesn't have metaboxes, so you'll configure it here.</p>

    <p><b>"Archive" pages</b> also don't have metaboxes, so Optimize Experiments for those also must be configured here.  Archive pages are lists of posts with a specific post type, or lists of posts within a specific category or taxonomy.</p>
    <div id="google-optimize-analytics-archive-list">
    <?php 
    $archive_pages = get_option( 'google-optimize-analytics-archive-pages' ) ? get_option( 'google-optimize-analytics-archive-pages' ) : array('archive_type' => '' );

    $new_offset = 0; // things can get out of order, so if what was #3 ends up being #2 because #1 was empty, they'll be saved in the array pos $new_offset
    foreach ($archive_pages as $archive_page) {
      if (empty($archive_page['archive_type']) && $new_offset > 0) {
        continue;
      }
      ?>
      <div class="google-optimize-analytics-archive-def">Archive type:<select name="google-optimize-analytics-archive-pages[<?php echo $new_offset; ?>][archive_type]"><option value="">Disabled</option><?php 
      $archive_types = array('post_type', 'category', 'taxonomy');
      foreach ($archive_types as $archive_type) {
        $selected = ($archive_page['archive_type'] == $archive_type) ? "selected" : '';
        echo "<option value=$archive_type $selected>$archive_type</option>"; 
      }
      echo '</select>  Slug (the post type, category, or taxonomy): <input class=goa_slug type=text name="google-optimize-analytics-archive-pages['. $new_offset .'][slug]" value = "' . (!empty($archive_page['slug']) ? $archive_page['slug'] : '') . '"> Term (optional extra for taxonomy if trying to do a page for a specific term): <input class=goa_second_level type=text name="google-optimize-analytics-archive-pages['. $new_offset .'][second_level]" value = "' . (!empty($archive_page['second_level']) ? $archive_page['second_level'] : '') . '">'; 
      if ($new_offset == 0) { 
        echo '<button class=cloner>Add more rows</button></div>';
      } else {
        echo '<button class=deleter>Remove row</button></div>';
      }
      $new_offset++;
    } ?>
    </div>
    <script>
      jQuery( function($) {
        var clonecount = $(".cloner").length;
        clonecount += $(".deleter").length;

        $("button.cloner").on("click", function(event) {
          event.preventDefault()
          clonecount++;
          var thisparent = $(this).parent("div");
          var newclone = $(thisparent).clone().appendTo("#google-optimize-analytics-archive-list");
          $('select', newclone).attr('name', "google-optimize-analytics-archive-pages["+clonecount+"][archive_type]");
          $('select option[value=""]', newclone).attr('selected', 'selected');
          $('.goa_slug', newclone).attr('name', "google-optimize-analytics-archive-pages["+clonecount+"][slug]").attr('value', '');
          $('.goa_second_level', newclone).attr('name', "google-optimize-analytics-archive-pages["+clonecount+"][second_level]").attr('value', '');
          $('button.cloner', newclone).toggleClass('cloner deleter').html('Remove row').show();
          
          $('button.deleter', newclone).on("click", function(event) {
            event.preventDefault()
            $(this).parent("div").remove();
            clonecount--;
          });
        });
        $("button.deleter").on("click", function(event) {
          event.preventDefault()
          $(this).parent("div").remove();
          clonecount--;
        });
      });
    </script>

    <p><b>To enable site-wide Google Optimize Experiments</b>, check this box: <input type="checkbox" name="google-optimize-on-all-pages" value="enable" <?php echo ($this->google_optimize_on_all_pages ? "checked" : ""); ?> /> WARNING: Checking the box will put extra javascript on every page of your site and will slighly delay the rendering of every page on your site.  ONLY check the box if you need to do a site-wide experiment. </p>

    <h3>Google Analytics Custom Fields</h3>
    <p>When setting up your experiment in Optimize, you'll be prompted to validate your Optimize snippet code.  You may get a warning that there's a mismatch with your GTM setup.   This is usually because your existing Google Analytics tracker in GTM has custom fields set -- find the Google Analytics tag in GTM and look under "fields to set".  If one of the following fields has some non-empty value in that Google Analytics tag in GTM, it must have the same value here.  <b>Leave the field blank if it isn't set in GTM!</b>  That said, it is common for "allowLinker" to be "true" and "cookieDomain" to be "auto" -- but again, check your GTM settings.</p><?php
    $ga_fields_to_set = $this->google_analytics_custom_field_defs;
    $ga_custom_fields = get_option( 'google-optimize-analytics-custom-fields' ) ? get_option( 'google-optimize-analytics-custom-fields' ) : array();

    // special case for importing legacy cookiedomain option into new structure
    if (empty($ga_custom_fields)) {
      $legacy_cookie_option = get_option('google-optimize-analytics-cookiedomain');
      if ($legacy_cookie_option) {
        $ga_custom_fields['cookieDomain'] = $legacy_cookie_option;
      }
    }

    foreach ($ga_fields_to_set as $field => $datatype) {
      $value = !empty($ga_custom_fields[$field]) ? $ga_custom_fields[$field] : '';
      $class = ($datatype != "string") ? "small-text" : "medium-text";
      echo "<span style='display:inline-block;'><label for $field>$field</label><input type=text class=$class name=google-optimize-analytics-custom-fields[$field] value=\"$value\"><i>($datatype)</i></span> &nbsp;&nbsp; <wbr />";
    }
    ?>

 
	  <?php submit_button(); ?>
  	</form>
    <?php 
  } 

	
  function register_gtm_settings(){
    register_setting( 'google-optimize-settings-group', 'google-optimize-id');
    register_setting( 'google-optimize-settings-group', 'google-optimize-analytics-id');
    register_setting( 'google-optimize-settings-group', 'google-optimize-on-all-pages');
    register_setting( 'google-optimize-settings-group', 'google-optimize-on-home');
    register_setting( 'google-optimize-settings-group', 'google-optimize-analytics-archive-pages');
    register_setting( 'google-optimize-settings-group', 'google-optimize-analytics-custom-fields');
	}


  function meta_box_setup() {
    if ($this->google_analytics_id && $this->google_optimize_id) {
      add_meta_box( 'gtm-google-optimize', 'Google Optimize Settings', array( $this, 'meta_box_content' ), get_post_types(), 'side', 'default' );
    }
  }

  public function meta_box_content() {
    global $post_id;

    $pagehide = get_post_meta( $post_id, '_google_optimize_pagehide', TRUE );

    $google_optimize_pagehide = !empty($pagehide) ? $pagehide : false;
    $checked = $google_optimize_pagehide ? 'checked' : '';

    $html = '<input type="hidden" name="gtm_google_optimize_nonce" id="gtm_google_optimize_nonce" value="' . wp_create_nonce( 'gtm_google_optimize_nonce' ) . '" />';
    $html .= '<input type="checkbox" name="_google_optimize_pagehide" id="_google_optimize_pagehide" value="true" ' . $checked . ' />';
    $html .= '<label for="_google_optimize_pagehide">Add Google Optimize snippets to this page</label><div class="description"><i>Required if doing an Optimize Experiment.  Will add some slight latency to the user experience, only check if actually doing an experiment.</i></div>';
    echo $html;
  }

  public function meta_box_save() {
    global $post_id;
    // Verify nonce
    if ( ! wp_verify_nonce( $_POST[ 'gtm_google_optimize_nonce'], 'gtm_google_optimize_nonce' ) ) {
      return $post_id;
    }

    // Verify user permissions
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
      return $post_id;
    }
    $value = isset( $_POST['_google_optimize_pagehide']) ? $_POST['_google_optimize_pagehide'] : false;
    update_post_meta( $post_id , '_google_optimize_pagehide', $value );
  }


  function snippet_should_be_included_on_page() {
    if ($this->google_optimize_on_all_pages) {
      // override anything else
      return TRUE;
    }
    if (is_single() || is_page()){ 
      $post_id = get_the_ID();
      $pagehide = get_post_meta( $post_id, '_google_optimize_pagehide' ) ? get_post_meta( $post_id, '_google_optimize_pagehide', true ) : FALSE;
      return $pagehide;
    }
    $optimize_on_home = (get_option( 'google-optimize-on-home' ) == "enable") ? TRUE : FALSE;
    if ($optimize_on_home) {
      if ( is_front_page() && is_home() ) {
        // Default homepage
        return TRUE;
      } elseif ( is_front_page() ) {
        // static homepage
        return TRUE;
      } elseif ( is_home() ) {
        // blog page
        return TRUE;
      }
    }
    if (is_archive()) {
      $archive_pages = get_option( 'google-optimize-analytics-archive-pages' ) ? get_option( 'google-optimize-analytics-archive-pages' ) : array();
      if (empty($archive_pages)) {
        return FALSE;
      }
      // for the moment only one archive page is possible but that's just a UI problem on the settings page
      // this code will handle multiple without change
      foreach ($archive_pages as $archive_page) {
        if (!empty($archive_page['archive_type']) && !empty($archive_page['slug'])) {
          switch($archive_page['archive_type']) {
            case 'post_type':
              if (is_post_type_archive()) {
                if (get_post_type() == $archive_page['slug']) {
                  return TRUE;
                }
              }
              break;
            case 'category':
              if (is_category($archive_page['slug'])) {
                return TRUE;
              }
              break;
            case 'taxonomy':
              if (!empty($archive_page['second_level'])) {
                if (is_tax($archive_page['slug'], $archive_page['second_level'])) {
                  return TRUE;
                } 
              } else {
                if (is_tax($archive_page['slug'])) {
                  return TRUE;
                }
              }
              break;
          }
        }
      }
    }
    return FALSE;  
  }


	function add_optimize_snippet_to_head() {
    if (empty($this->google_analytics_id) || empty($this->google_optimize_id)) {
      // required fields missing, return
      return;
    }
    if (!$this->snippet_should_be_included_on_page()) {
      return;
    }
    $ga_fields_to_set = $this->google_analytics_custom_field_defs;
    $ga_custom_fields = get_option( 'google-optimize-analytics-custom-fields' ) ? get_option( 'google-optimize-analytics-custom-fields' ) : array();
    if (empty($ga_custom_fields)) {
      $custom_field_json = '';
    } else {
      $custom_field_array = array();
      foreach ($ga_fields_to_set as $field => $datatype) {
        if (!empty($ga_custom_fields[$field])) {
          $value = $ga_custom_fields[$field];
          settype($value, $datatype);
          $custom_field_array[$field] = $value;
        }
      }
      $custom_field_json = "," .  json_encode($custom_field_array, JSON_UNESCAPED_UNICODE);
    }
      
    echo "<!-- GTM:OPTIMIZE_PAGE_HIDE --><style>.async-hide { opacity: 0 !important} </style>
<script>(function(a,s,y,n,c,h,i,d,e){s.className+=' '+y;h.start=1*new Date;
h.end=i=function(){s.className=s.className.replace(RegExp(' ?'+y),'')};
(a[n]=a[n]||[]).hide=h;setTimeout(function(){i();h.end=null},c);h.timeout=c;
})(window,document.documentElement,'async-hide','dataLayer',4000,
{'" .  $this->google_optimize_id . "':true});</script>";
    echo "<!-- GOOGLE_ANALYTICS_OPTIMIZE_SNIPPET -->
<script>
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
ga('create', '" . $this->google_analytics_id . "'" . $custom_field_json . ");";
    echo "ga('require', '" . $this->google_optimize_id . "');
</script>";
  }
}

$ieg_gos = new IEG_Google_Optimize_Snippet();
