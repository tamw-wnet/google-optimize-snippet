<?php
/*
Plugin Name: Google Optimize Snippet
Description: Sets up the Google Optimize Snippet and provides a checkbox to add it on any page or post.
Version: 1.2
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
	  $page = add_options_page('Google Optimize Snippet', 'Google Optimize Snippet', 'manage_options', 'google-optimize-settings', array( $this, 'google_optimize_admin') );
  	add_action( 'admin_init', array( $this, 'register_gtm_settings' ) );
  }

  function google_optimize_admin(){ 
    ?>
	  <h1>Google Optimize Snippet Setup</h1>
	  <form method="post" action="options.php">
  	<?php settings_fields( 'google-optimize-settings-group' );
	  do_settings_sections( 'google-optimize-settings-group' );?>

    <p>This plugin does the work of installing the necessary Google Optimize 'snippets' -- both the 'page-hiding' snippet and the Optimize container snippet -- in the correct place on the pages you want to run Google Optimize Experiments, but without having to directly edit your theme files.  This plugin is based on the Optimize documentation <a href="https://support.google.com/optimize/answer/7359264" target=_new>here</a> and implements Google's recommended means of getting the Optimize snippets onto your pages.</p>

    <h3>Google Tag Manager Integration</h3>
    <p>This plugin assumes that you <ol>
      <li>Have Google Tag Manager (GTM) code already installed and working on your pages,</li>
      <li>Are using GTM to include your Google Analytics tracker, firing for every page view, and</li>
      <li>Are NOT using GTM to implement the Google Optimize code and 'page hiding snippet'.</li>
    </ol>
    You shouldn't need to make any changes to your current GTM setup to use this plugin.  This plugin will handle getting the Google Optimize code onto your pages.</p>

    <h3>Setting up and configuring Google Optimize</h3>
    <p>Running Google Optimize Experiments requires both your Google Analytics Property ID -- the same Property ID you have deployed via Google Tag Manager -- and also a special Google Optimize Container ID that you'll get when you set up your Google Optimize account.  Visit <a href="https://www.google.com/analytics/optimize/" target =_new>Google Optimize</a> to set up your account, manage your container(s), and create and run experiments.</p>

    <h2>Google Settings</h2>
    <p>Before the Optimize code will appear on any pages, you must first enter values in the boxes below:</p>
    <p><b>Google Optimize Container ID: </b> <input type="text" name="google-optimize-id" value="<?php echo $this->google_optimize_id; ?>">
    <span class="description">This will start with GTM-something, but will NOT be the same value as the Google Tag Manager ID.  You should only have one Container per website. You will get this vallue from  <a href="https://www.google.com/analytics/optimize/" target =_new>Google Optimize</a>.</span></p>
    <p><b>Google Analytics Property ID: </b> <input type="text" name="google-optimize-analytics-id" value="<?php echo $this->google_analytics_id; ?>">
    <span class="description">This will start with UA-something.  You will NOT remove the Google Analytics Property ID tag from your GTM setup. This Analytics property will match the Google Analytics property where you track your experiment results.  You will get this value from <a href="https://analytics.google.com/analytics/web/" target =_new>Google Analytics</a>.</span></p>

    <h2>Placing the Optimize Codeblock</h2>
    <p class="description">The sections below handle placing both the 'page-hiding' snippet and the Google Optimize container snippet onto the appropriate places in your website.  <b>You should only place the Optimize codeblock on pages that you are using Optimize to make changes to.</b></p>

    <h3>Posts, Pages, and Custom Post Type items</h3>
    <p>Go into the individual pages, posts, or custom post types in the WordPress editor view to check the 'Add Google Optimize Snippets' box on those items.</p>

    <h3>Home Page or Front Page</h3>
    <?php $optimize_on_home = (get_option( 'google-optimize-on-home' ) == "enable") ? TRUE : FALSE; ?>
    <p>To place the Optimize code snippets on the front/home page of your website <b>check this box:</b> <input type="checkbox" name="google-optimize-on-home" value="enable" <?php echo ($optimize_on_home ? "checked" : ""); ?> /></p>

    <h3>Archive Pages</h3>
    <p>Archive pages are lists of posts with a specific post type, or lists of posts within a specific category or taxonomy. To place the Optimize code snippets on an archive page, pick an archive type ("post_type", "category", or "taxonomy" and enter the slug (eg "posts", "entertainment", or "directors").  For "taxonomy" archives, optionally enter the term (eg "archive type: taxonomy; slug: directors; term: sofia-coppola").  The "term" field will only affect taxonomy archives.  Multiple archive pages can be specified, click 'add more rows' below.</p>
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
      echo '</select>  Slug: <input class=goa_slug type=text name="google-optimize-analytics-archive-pages['. $new_offset .'][slug]" value = "' . (!empty($archive_page['slug']) ? $archive_page['slug'] : '') . '"> Term (optional): <input class=goa_second_level type=text name="google-optimize-analytics-archive-pages['. $new_offset .'][second_level]" value = "' . (!empty($archive_page['second_level']) ? $archive_page['second_level'] : '') . '">'; 
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
        var clonecount = $(".google-optimize-analytics-archive-def .deleter").length;

        $(".google-optimize-analytics-archive-def button.cloner").on("click", function(event) {
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
        $(".google-optimize-analytics-archive-def button.deleter").on("click", function(event) {
          event.preventDefault()
          $(this).parent("div").remove();
          clonecount--;
        });
      });
    </script>



    <h3>Other custom paths</h3>
    <p>It is possible to have a location on your site that isn't a post, page, custom post type, home/front page, or archive.  This would be a very unusual situation, such as the login page for your site.  Enter the path(s), without either beginning or trailing slash, below.  <i>eg: <?php echo site_url('members/login/'); ?> would be 'members/login'</i></p>
    <div id="google-optimize-custom-path-list">
    <?php
    $custom_paths = get_option( 'google-optimize-custom-paths' ) ? get_option( 'google-optimize-custom-paths' ) : array('0' =>'');

    $new_offset = 0; // things can get out of order, so if what was #3 ends up being #2 because #1 was empty, they'll be saved in the array pos $new_offset
    foreach ($custom_paths as $custom_path) {
      if (empty($custom_path) && $new_offset > 0) {
        continue;
      }
      ?>
      <div class="google-optimize-custom-path">
      Path: <input type=text class=regular-text name="google-optimize-custom-paths['<?php echo $new_offset; ?>']" value = "<?php echo (!empty(trim($custom_path)) ? trim($custom_path) : ''); ?>"><?php
      if ($new_offset == 0) {
        echo '<button class=cloner>Add more paths</button></div>';
      } else {
        echo '<button class=deleter>Remove path</button></div>';
      }
      $new_offset++;
    } ?>
    </div>
    <script>
      jQuery( function($) {
        var pathcount = $(".google-optimize-custom-path .deleter").length;

        $(".google-optimize-custom-path button.cloner").on("click", function(event) {
          event.preventDefault()
          pathcount++;
          var thisparent = $(this).parent("div");
          var newclone = $(thisparent).clone().appendTo("#google-optimize-custom-path-list");
          $('input', newclone).attr('name', "google-optimize-custom-paths['"+pathcount+"']").attr('value', '');
          $('button.cloner', newclone).toggleClass('cloner deleter').html('Remove path').show();

          $('button.deleter', newclone).on("click", function(event) {
            event.preventDefault()
            $(this).parent("div").remove();
            pathcount--;
          });
        });
        $(".google-optimize-custom-path button.deleter").on("click", function(event) {
          event.preventDefault()
          $(this).parent("div").remove();
          pathcount--;
        });
      });
    </script>

    <h3>Site-wide</h3>
    <p>To enable site-wide Google Optimize Experiments <b>check this box:</b> <input type="checkbox" name="google-optimize-on-all-pages" value="enable" <?php echo ($this->google_optimize_on_all_pages ? "checked" : ""); ?> /> <br />WARNING: Checking the box will put extra javascript on every page of your site and will slighly delay the rendering of every page on your site.  ONLY check the box if you need to do a site-wide experiment; an example might be a change to your global navigation. </p>

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
      echo "<span class='google_optimize_custom_field' style='display:inline-block;'><label for $field>$field</label><input type=text class=$class name=google-optimize-analytics-custom-fields[$field] value=\"$value\"><i>($datatype)</i></span> &nbsp;&nbsp; <wbr />";
    }
    ?>

 
	  <?php submit_button(); ?>
  	</form>
    <h3>FAQ</h3>
    <dl>
    <dt><b>What if I don't use Google Tag Manager on my website?</b></dt>
    <dd>You can still use this plugin, as-is. Just make sure that you're still including Google Analytics code on all of the pages of your website, including a tracker with the Google Analytics Property ID you entered in the Google Settings section above.</dd> 
    <dt><b>I'm trying to get the snippets on an archive page, what's this 'slug' you talk about?</b></dt>
    <dd>For custom post types, go to the admin for that post type (where you see a list of that post type) and look in the address bar.  The address will look something like <code><?php echo admin_url(); ?>/edit.php?post_type=<b>some_slug</b></code> -- the "post_type" value will be the slug (for the built in "post" post_type, the slug is "post".  Category slugs can be found in the <a href="<?php echo admin_url('edit-tags.php?taxonomy=category'); ?>">list of Categories</a> in your admin, and if you have any custom taxonomies set up, you'll find the slug for that taxonomy in the address bar in the list of that taxonomy (<code><?php echo admin_url(); ?>/edit_tags.php?taxonomy=<b>some_slug</b></code>).</dd>
    <dt><b>I don't understand how to run Google Optimize Experiments</b></dt>
    <dd>This plugin only gets the necessary infrastructure installed, but you'll still need to create and manage your experiments via the <a href="https://www.google.com/analytics/optimize/" target =_new>Google Optimize</a> website.  The instructions there are pretty dry and don't really explain the core concepts of experimentation.  <a href="https://support.google.com/optimize/answer/7012154"  target =_new>This article on the Google Optimize Help site</a> is a pretty good entry point that explains the types of experiments you can do and how to run them.</dd>
    </dl>
    <?php 
  } 

	
  function register_gtm_settings(){
    register_setting( 'google-optimize-settings-group', 'google-optimize-id');
    register_setting( 'google-optimize-settings-group', 'google-optimize-analytics-id');
    register_setting( 'google-optimize-settings-group', 'google-optimize-on-all-pages');
    register_setting( 'google-optimize-settings-group', 'google-optimize-on-home');
    register_setting( 'google-optimize-settings-group', 'google-optimize-analytics-archive-pages');
    register_setting( 'google-optimize-settings-group', 'google-optimize-custom-paths');
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
      // archive page but not matching the archive_pages array
      return FALSE;
    }
    // lastly, check the custom_urls array for things that aren't posts, pages, home, or archives
    // this case should only get reached on very rare cases since we've done return on all the other ones
    $custom_paths = get_option( 'google-optimize-custom-paths' ) ? get_option( 'google-optimize-custom-paths' ) : array();

    if (!empty($custom_paths)) {
      global $wp;
      $current_path = $wp->request;
      foreach ($custom_paths as $custom_path) {
        if ($current_path == $custom_path) {
          return TRUE;
        }
      }
      return FALSE;
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
