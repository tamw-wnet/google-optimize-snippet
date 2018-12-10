<?php
/*
Plugin Name: Google Optimize Snippet
Description: Sets up the Google Optimize Snippet and provides a checkbox to add it on any page or post.
Version: 1.0
Author: Will Tam
*/

class IEG_Google_Optimize_Snippet {
  private $google_optimize_id;
  private $google_analytics_id;
  private $google_analytics_cookiedomain;

  public function __construct() {
    $this->google_optimize_id = get_option( 'google-optimize-id' ) ? get_option( 'google-optimize-id' ) : '';
    $this->google_analytics_id = get_option( 'google-optimize-analytics-id' ) ? get_option( 'google-optimize-analytics-id' ) : '';
    $this->google_optimize_on_all_pages = (get_option( 'google-optimize-on-all-pages' ) == "enable") ? TRUE : FALSE;
    $this->google_analytics_cookiedomain = get_option( 'google-optimize-analytics-cookiedomain' ) ? get_option( 'google-optimize-analytics-cookiedomain' ) : '';

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

    <p>Running Google Optimize Experiments requires both your Google Analytics Property ID -- that you normally would just deploy via Google Tag Manager -- and also a special Google Optimize Container ID to be setup.  Visit <a href="https://www.google.com/analytics/optimize/" target =_new>Google Optimize</a> for more details.</p>
    <p>Enter the Google Optimize container id for this blog:<br /><span style="color:#999;">(will also start with GTM-something, but will NOT be the same value as the Google Tag Manager id)</span></p>
    <input type="text" name="google-optimize-id" value="<?php echo $this->google_optimize_id; ?>">   
    <p>Also required to do experiments: enter the Google Analytics Property id for this blog:<br /><span style="color:#999;">(will start with UA-something.  You will NOT remove the Google Analytics Property ID tag from your GTM setup)</span></p>
    <input type="text" name="google-optimize-analytics-id" value="<?php echo $this->google_analytics_id; ?>">

    <p>To enable Google Optimize Experiments on individual pages, you'll need to fill in both fields above AND check the 'Add Google Optimize Code' box on those pages.</p>

    <p>To enable site-wide Google Optimize Experiments, check this box: <input type="checkbox" name="google-optimize-on-all-pages" value="enable" <?php echo ($this->google_optimize_on_all_pages ? "checked" : ""); ?> /> WARNING: Checking the box will put extra javascript on every page of your site and will slighly delay the rendering of every page on your site.  ONLY check the box if you need to do a site-wide experiment. </p>


     <p>ONLY If there's a specified cookieDomain set for this Google Analytics Property in GTM, enter the same value here:<br /><span style="color:#999;">(This is so very rare it would be set.  'auto' is a possible value, but just leave it blank unless running into specific cookieDomain mismatch warnings in Google Optimize.)</span></p>
    <input type="text" name="google-optimize-analytics-cookiedomain" value="<?php echo $this->google_analytics_cookiedomain ?>">

 
	  <?php submit_button(); ?>
  	</form>
    <?php 
  } 

	
  function register_gtm_settings(){
    register_setting( 'google-optimize-settings-group', 'google-optimize-id');
    register_setting( 'google-optimize-settings-group', 'google-optimize-analytics-id');
    register_setting( 'google-optimize-settings-group', 'google-optimize-on-all-pages');
    register_setting( 'google-optimize-settings-group', 'google-optimize-analytics-cookiedomain');
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



	function add_optimize_snippet_to_head() {
    $post_id = get_the_ID();
    $pagehide = get_post_meta( $post_id, '_google_optimize_pagehide' ) ? get_post_meta( $post_id, '_google_optimize_pagehide', true ) : 0;
    if ($pagehide && $this->google_analytics_id && $this->google_optimize_id) {
      echo "<!-- GTM:OPTIMIZE_PAGE_HIDE --><style>.async-hide { opacity: 0 !important} </style>
<script>(function(a,s,y,n,c,h,i,d,e){s.className+=' '+y;h.start=1*new Date;
h.end=i=function(){s.className=s.className.replace(RegExp(' ?'+y),'')};
(a[n]=a[n]||[]).hide=h;setTimeout(function(){i();h.end=null},c);h.timeout=c;
})(window,document.documentElement,'async-hide','dataLayer',4000,
{'" .  $this->google_optimize_id . "':true});</script>";
      $cookiedomainstring = !empty($this->google_analytics_cookiedomain) ? ",'" . $this->google_analytics_cookiedomain . "'" : '';
      echo "<!-- GOOGLE_ANALYTICS_OPTIMIZE_SNIPPET -->
<script>
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
ga('create', '" . $this->google_analytics_id . "'" . $cookiedomainstring . ",{allowLinker:true});";
      echo "ga('require', '" . $this->google_optimize_id . "');
</script>";
    }
  }

}

$ieg_gos = new IEG_Google_Optimize_Snippet();
