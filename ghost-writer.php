<?php
/*
Plugin Name: Ghost Writer
Description: Ghostwriter overrides WordPress’s author pages and feeds to emulate the full functionality provided to WordPress users. Ghostwriter was developed by Floate in Melbourne. Just north of the river.
Version: 1.0
Author: Floate Design Partners
Author URI: http://floate.com.au/

*/


Class FDP_GhostAuthors {

	private $ghost_author_thumb_width;
	private $ghost_author_thumb_height;
	private $ghost_author_thumb_crop;
	private $the_ghost_author;


	function __construct(){

		$this->ghost_author_thumb_width = apply_filters( 'fdp_ghostauthors_thumb_width', 60);
		$this->ghost_author_thumb_height = apply_filters( 'fdp_ghostauthors_thumb_height', 60);
		$this->ghost_author_thumb_crop = apply_filters( 'fdp_ghostauthors_thumb_crop', true);

		add_action( 'init', array(&$this, 'register_content_type'));
		add_action( 'after_setup_theme', array(&$this, 'register_thumbnail'));

		//modify default query
		add_action('pre_get_posts', array(&$this, 'filter_pre_get_posts'));

		//add meta boxes
		add_action( 'admin_init', array(&$this, 'post_meta_box_init') );
		add_action( 'save_post', array(&$this, 'post_meta_box_save') );
		// add_action( 'admin_init', array(&$this, 'ghostauthor_meta_box_init') );
		// add_action( 'save_post', array(&$this, 'ghostauthor_meta_box_save') );
		
		/* filter get_the_author_meta */
		// to display the name
		$filters = array (
			'get_the_author_user_login',
			'get_the_author_user_nicename',
			'get_the_author_display_name',
			'get_the_author_nickname',
			'get_the_author_first_name',
			'get_the_author_last_name',
			'get_the_author_user_firstname',
			'get_the_author_user_lastname',
			'the_author'
		);
		foreach ( $filters as $filter ) {
			add_filter ( $filter, array(&$this, 'filter_the_author_name') );
		}
		
		// to display the bio
		$filters = array (
			'get_the_author_user_description',
			'get_the_author_description'
		);
		foreach ( $filters as $filter ) {
			add_filter ( $filter, array(&$this, 'filter_the_author_description') );
		}

		// to get the URL
		$filters = array (
			'author_link'
		);
		foreach ( $filters as $filter ) {
			add_filter ( $filter, array(&$this, 'filter_the_author_url') );
		}
		
		// to get the email
		$filters = array (
			'get_the_author_user_email'
		);
		foreach ( $filters as $filter ) {
			add_filter ( $filter, array(&$this, 'filter_the_author_email') );
		}
		
		// to get the avatar
		$filters = array (
			'get_avatar'
		);
		foreach ( $filters as $filter ) {
			add_filter ( $filter, array(&$this, 'filter_the_author_avatar'), 10, 5 );
		}


		register_activation_hook( __FILE__, array(&$this, 'setup_defaults') );
		
	}

	function register_content_type() {
		$ghost_author_args = array(
			'public'		=> true,
			'label'			=> 'Ghost Authors',
			'description' 	=> 'Ghost Authors',
			'publicly_queryable'=> true,
			'exclude_from_search' => true,
			'show_ui'		=> true,
			'show_in_menu'	=> true,
			'menu_position' => 30,
			'hierarchical'	=> false,
			'has_archive'	=> false,
			'rewrite' => array('slug' => 'fdp-ghost-dashboard-placeholder', 'feeds' => false), // Permalinks
			'query_var' => "fdp-ghost-dashboard-placeholder" // This goes to the WP_Query schema
		);
		$ghost_author_args['labels'] = array(
			'name'			=> 'Ghost Authors',
			'singular_name' => 'Ghost Authors',
			'add_new_item'	=> 'Add new ghost author',
			'edit_item'		=> 'Edit ghost author',
			'new_item'		=> 'New ghost author',
			'view_item'		=> 'View ghost author',
			'search_items'	=> 'Search ghost author',
			'not_found'		=> 'No ghost authors found',
			'not_found_in_trash' => 'No ghost authors in trash',
			'menu_name'		=> 'Ghost Authors'
		);
		$ghost_author_args['supports'] = array(
			'title',
			'slug',
			'thumbnail',
			'excerpt'
		);
		register_post_type( 'fdp_ghost_authors', $ghost_author_args );
	}

	function register_thumbnail() {
		if ( function_exists( 'add_theme_support' ) ) {
			// always support thumbnails in ghost authors
			// add_theme_support and current_theme_supports added in 2.9.0
			$supported = array( 'fdp_ghost_authors' );

			$post_types = get_post_types();
			foreach ($post_types as $type) {
				if ( current_theme_supports( 'post-thumbnails', $type ) AND ( $type != 'fdp_ghost_authors' ) ) {
					$supported[] = $type;
				}
			}
			if ( function_exists( 'remove_theme_support') ) {
				// remove_theme_support added in 3.0.0
				remove_theme_support( 'post-thumbnails' );
			}
			add_theme_support( 'post-thumbnails', $supported );
		}

		add_image_size( 
			'ghost-author-thumb', 
			$this->ghost_author_thumb_width, 
			$this->ghost_author_thumb_height, 
			$this->ghost_author_thumb_crop
		);
	}

	function filter_pre_get_posts(&$query) {
		if ( $query->is_main_query() AND $query->is_author ) {
			//take over the author page
			$the_ghost_author = &$this->the_ghost_author;

			//get the author data
			$the_ghost_author = new WP_Query(array(
				'post_type' => 'fdp_ghost_authors',
				'name' => $query->query['author_name'],
				'posts_per_page' => 1
			));
			$the_ghost_author->get_queried_object();


			// echo '<pre>';
			// print_r($query);
			// echo '</pre><hr>';

			$query->set('author_name');
			$query->set('meta_key', '_fdp_ghost_author_id');
			$query->set('meta_value', $the_ghost_author->queried_object_id);

			// echo '<pre>';
			// print_r($query);
			// echo '</pre><hr>';

			// exit;
		}
		elseif ( $query->is_main_query() AND $query->is_single AND ($query->query_vars['post_type'] == 'fdp_ghost_authors') ) {
			//add logically false condition to where filter
			add_filter ( 'posts_where', array(&$this, 'filter_the_queries_where') );
		}
	}

	function filter_the_queries_where($where) {
		$where = ' AND 1=0 ' . $where;
		//remove filter so it runs on main query only.
		remove_filter ( 'posts_where', array(&$this, 'filter_the_queries_where') );
		return $where;
	}

	function post_meta_box_init() {
		add_meta_box(
			'fdp_ghost_authors', 
			'Set ghost author', 
			array(&$this, 'post_meta_box_display'), 
			'post', 
			'normal', 
			'core');
	}

	function post_meta_box_display() {
		global $post;
		$ghost_author_list = new WP_Query(array(
			'post_type' => 'fdp_ghost_authors',
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC'
		));


		$ghost_author_selected = get_post_meta($post->ID, '_fdp_ghost_author_id', true);
		?>
		<input type="hidden" name="fdp_ghost_author_nonce" value="<?php echo wp_create_nonce( 'fdp_ghost_author_nonce_B73A' ); ?>" />
		<select name="fdp_ghost_author_list">
		<option value="">Select</option>
		<?php
		if ($ghost_author_list->have_posts()) : 
			while ($ghost_author_list->have_posts()) : 
				$ghost_author_list->the_post();
				echo '<option value="';
				echo $post->ID;
				echo '" ';
				if ($ghost_author_selected == $post->ID) {
					echo 'selected="selected" ';
				}
				echo '>';
				echo esc_attr($ghost_author_list->post->post_title);
				echo "</option>";
			endwhile;
		endif;
		wp_reset_postdata();
		?>
		</select>
		<?php
	}

	function post_meta_box_save($post_id){
		if ( !wp_verify_nonce( $_POST['fdp_ghost_author_nonce'],'fdp_ghost_author_nonce_B73A') ) {
			return $post_id;
		}

		// verify if this is not an auto save routine.
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Check post type and permissions
		if ( ('post' != $_POST['post_type']) OR (!current_user_can( 'edit_post', $post_id )) ) {
			return $post_id;
	 	}

		update_post_meta($post_id, '_fdp_ghost_author_id', $_POST['fdp_ghost_author_list']);
	}

	function ghostauthor_meta_box_init() {
		add_meta_box(
			'fdp_ghost_authors', 
			'Ghost author details', 
			array(&$this, 'ghostauthor_meta_box_display'), 
			'fdp_ghost_authors', 
			'normal', 
			'core');
	}

	function ghostauthor_meta_box_display() {

		$ghost_author_metadata = (int) get_post_meta($post->ID, '_fdp_ghost_author_metadata', true);

		?>
		<input type="hidden" name="fdp_ghost_author_noncevalue" value="<?php echo wp_create_nonce( 'fdp_ghost_author_nonce_B73B' ); ?>" />
		
		<?php
	}

	function ghostauthor_meta_box_save($post_id){
		if ( !wp_verify_nonce( $_POST['fdp_ghost_author_noncevalue'],'fdp_ghost_author_nonce_B73B') ) {
			return $post_id;
		}

		// verify if this is not an auto save routine.
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Check post type and permissions
		if ( ('fdp_ghost_authors' != $_POST['post_type']) OR (!current_user_can( 'edit_post', $post_id )) ) {
			return $post_id;
	 	}
	
		$metadata = array(
			
		);

		update_post_meta($post_id, '_fdp_ghost_author_metadata', $_POST['fdp_ghost_author_list']);
	}

	function filter_the_author_name($output){
		global $post;
		if ( is_admin() ) {
			return $output;
		}
		
		$ghost_author_set = (int) get_post_meta($post->ID, '_fdp_ghost_author_id', true);
		if ( ( $ghost_author_set == 0 ) AND ( (int) $post->post_parent != 0 ) ) {
			$ghost_author_set = (int) get_post_meta($post->post_parent, '_fdp_ghost_author_id', true);
		}
		
		
		$the_ghost_author = new WP_Query(array(
			'post_type' => 'fdp_ghost_authors',
			'p' => $ghost_author_set
		));
		
		// echo '<pre>';
		// print_r($the_ghost_author);
		// echo '</pre><hr>';

		// exit;
		
		if ( $the_ghost_author->have_posts() ) {
			$the_ghost_author->the_post();
			$output = get_the_title();
		}
		wp_reset_postdata();
		
		return $output;
	}

	function filter_the_author_description($output){
		global $post;
		if ( is_admin() ) {
			return $output;
		}
		$ghost_author_set = (int) get_post_meta($post->ID, '_fdp_ghost_author_id', true);
		if ( ( $ghost_author_set == 0 ) AND ( (int) $post->post_parent != 0 ) ) {
			$ghost_author_set = (int) get_post_meta($post->post_parent, '_fdp_ghost_author_id', true);
		}

		$the_ghost_author = new WP_Query(array(
			'post_type' => 'fdp_ghost_authors',
			'p' => $ghost_author_set
		));
		
		// echo '<pre>';
		// print_r($post);
		// echo '</pre><hr>';

		// exit;
		
		if ( $the_ghost_author->have_posts() ) {
			$the_ghost_author->the_post();
			$output = get_the_excerpt();
		}
		wp_reset_postdata();
		
		return $output;
	}
	
	function filter_the_author_url($output) {
		global $post;
		if ( is_admin() ) {
			return $output;
		}
		$ghost_author_set = (int) get_post_meta($post->ID, '_fdp_ghost_author_id', true);
		if ( ( $ghost_author_set == 0 ) AND ( (int) $post->post_parent != 0 ) ) {
			$ghost_author_set = (int) get_post_meta($post->post_parent, '_fdp_ghost_author_id', true);
		}

		$the_ghost_author = new WP_Query(array(
			'post_type' => 'fdp_ghost_authors',
			'p' => $ghost_author_set
		));

		if ( $the_ghost_author->have_posts() ) {
			$the_ghost_author->the_post();
			global $wp_rewrite;
			$link = $wp_rewrite->get_author_permastruct();
			$ghost_slug = $post->post_name;
			
			if ( empty($link) ) {
				$file = home_url( '/' );
				$link = $file . '?author=' . $ghost_slug;
			} else {
				$link = str_replace('%author%', $ghost_slug, $link);
				$link = home_url( user_trailingslashit( $link ) );
			}

			
			$output = $link;
		}
		wp_reset_postdata();
		
		return $output;
	}

	function filter_the_author_email($output) {
		return 'ghost author -- use post thumbnail';
	}

	function filter_the_author_avatar($output, $id_or_email, $size, $default, $alt) {
		// echo 'hi'; exit;
		global $post;
		if ( is_admin() ) {
			return $output;
		}
		elseif ($id_or_email == 'ghost author -- use post thumbnail') {
			$ghost_author_set = (int) get_post_meta($post->ID, '_fdp_ghost_author_id', true);
			if ( ( $ghost_author_set == 0 ) AND ( (int) $post->post_parent != 0 ) ) {
				$ghost_author_set = (int) get_post_meta($post->post_parent, '_fdp_ghost_author_id', true);
			}

			$the_ghost_author = new WP_Query(array(
				'post_type' => 'fdp_ghost_authors',
				'p' => $ghost_author_set
			));

			if ( $the_ghost_author->have_posts() AND current_theme_supports( 'post-thumbnails', 'fdp_ghost_authors' ) ) {
				$the_ghost_author->the_post();
				
				$thumbnail = get_the_post_thumbnail($post->ID, 'ghost-author-thumb', array(
					'alt' => $alt,
					'title' => $alt,
					'class' => 'avatar photo'
				));
				
			
				$output = $thumbnail;
			}
			wp_reset_postdata();
		}
		return $output;
	}

	function setup_defaults() {
		add_action( 'after_setup_theme', array(&$this, 'setup_defaults_with_data'), 20);
		if ( !post_type_exists('fdp_ghost_authors') ) {
			$this->register_content_type();
		}
		
		//add default author
		global $user_ID;
		$the_default_title = get_bloginfo('name');
		$new_post = array(
			'post_title' => $the_default_title,
			'post_content' => '',
			'post_status' => 'publish',
			'post_date' => date('Y-m-d H:i:s'),
			'post_author' => $user_ID,
			'post_type' => 'fdp_ghost_authors'
		);
		
		global $post;
		
		
		$first_ghostauthor = new WP_Query(array(
			'post_type' => 'fdp_ghost_authors',
			'posts_per_page' => 1
		));
		
		if ( !$first_ghostauthor->have_posts() ) {
			//only add authors if none exist already
			$ghostauthor_id = wp_insert_post($new_post);
			//add default author to all posts
			// doesn't get ghost authors because 'exclude_from_search' is true
			$all_posts = new WP_Query(array(
				'post_type' => 'post',
				'posts_per_page' => -1
			));

			while ( $all_posts->have_posts() ) : $all_posts->the_post();
				update_post_meta($post->ID, '_fdp_ghost_author_id', $ghostauthor_id);
			endwhile;
		}
		
		wp_reset_postdata();

	}
	
}


$fdp_ghostauthors = new FDP_GhostAuthors;







?>