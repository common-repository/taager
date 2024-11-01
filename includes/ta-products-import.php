<?php

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_taager_products_import', 'ta_product_import_ajax' );
function ta_product_import_ajax() {
	$form_input_category = sanitize_text_field($_POST['product_category']);
	$form_input_name = sanitize_text_field($_POST['product_name']);
	$form_input_ids = sanitize_text_field($_POST['product_ids']);
	$response = taager_import_products_from_db( $form_input_category, $form_input_name, $form_input_ids );
	if ( true == $response['import'] ) {
		$existing_product = '';
		if ( $response['existing_count'] > 0 ) {
			$product_text = 'product';
			$was          = 'was';
			$exist_text   = 'exists';
			if ( 1 < $response['existing_count'] ) {
				$product_text = 'products';
				$exist_text   = 'exist';
				$was          = 'were';
			}

			//$existing_product = '<div class="existing_products_list">' . $response['existing_count'] . ' ' . $product_text . ' ' . $was . ' already imported before. List of those products are: <ul>';
			$existing_product = '<div class="existing_products_list">' . $response['existing_count'] . ' ' . __('Products that were already imported', 'taager-plugin') . '<ul>';
			foreach ( $response['existing_post'] as $product ) {
				$existing_product .= "<li>{$product}</li>";
			}
			$existing_product .= '</ul></div>';
		}
		echo '<p>' . esc_html($response['imported_count']) . ' ' .  __('Imported products', 'taager-plugin') . '</p>' . $existing_product;
	}
	die;
}

/**
 * Import products from backend
 */
function taager_import_products_from_db( $product_category, $product_name , $product_ids) {
	// Set unlimited execution time
	set_time_limit( 0 );
	global $wpdb;

	$cat_param   = ( $product_category ) ? array('category_name_ar' =>  $product_category ) : null;
	$pname_param = ( $product_name ) ? array('prod_name' =>  $product_name ) : null;
	$pid_param = ( $product_ids ) ? array('prod_ids' =>  $product_ids ) : null;
	
	$mixpanel_event_properties = array();

	if($product_ids) {
		$products_api_response = taager_call_API( 'GET', taager_get_url('IMPORT_PRODUCTS', $pid_param));	
		$mixpanel_event_properties['Product id'] = $product_ids;
		$product_import_method = 'Product id';
	} elseif($product_name) {
		$products_api_response = taager_call_API( 'GET', taager_get_url('IMPORT_PRODUCTS', $pname_param));	
		$mixpanel_event_properties['Product name'] = $product_name;
		$product_import_method = 'Product name';
	} elseif ($product_category) {
		$products_api_response = taager_call_API( 'GET', taager_get_url('IMPORT_PRODUCTS', $cat_param));
		$mixpanel_event_properties['Category'] = $product_category;
		$product_import_method = 'Category';
	}

	$api_request_time = time();

	$mixpanel_event_properties['Import method'] = $product_import_method;

	ta_track_event('taager_WP_import_products', $mixpanel_event_properties);
	
	$existing_posts               = [];
	$count_import_product = 0;
	for ( $i = 0; $i < count( $products_api_response->data ); $i++ ) {
		$product = $products_api_response->data[ $i ];
		
		if ( $product_category || $product_name) {
			$pos = stripos( $product->productName, $product_name );

			if ( $product_category && $product_name ) {
				if ( ( $product->category->text != $product_category ) && ( $pos === false ) ) {
					continue;
				}
			} elseif ( $product_name ) {
				if ( $pos === false ) {
					continue;
				}
			}
		}
		
		if ( $product_category || $product_ids) {
			$pos = stripos( $product->prodID, $product_ids );

			if ( $product_category && $product_ids ) {
				if ( ( $product->category->text != $product_category ) && ( $pos === false ) ) {
					continue;
				}
			} elseif ( $product_ids ) {
				if ( $pos === false ) {
					continue;
				}
			}
		}

		$sku       = $product->prodID;

		$data       = taager_generate_product_data( $product );
		$existing_wc_products = taager_get_posts_by_sku( $sku, 'product' );
		$existing_wc_product_variations = taager_get_posts_by_sku($sku, 'product_variation');

		$insert_product_data = array(
			'post_title'   => $product->productName,
			'post_content' => $product->specifications,
			'post_excerpt' => $product->productDescription,
		);

		if ( $existing_wc_products ) {
			$existing_posts[] = $product->productName;
			unset($insert_product_data['post_title']);
			foreach($existing_wc_products as $existing_wc_product) {
				$insert_product_data['ID'] = $existing_wc_product->ID;
				$product_id                = wp_update_post( $insert_product_data );

				// meta data to identify that product come from Taager plugin
				update_post_meta( $existing_wc_product->ID, 'taager_product', 1 );
				$stock_status = $product->isProductAvailableToSell? 'instock' : 'outofstock';
				update_post_meta( $existing_wc_product->ID, '_stock_status', $stock_status );
				$current_product_price = get_post_meta( $existing_wc_product->ID, '_regular_price', true );
				if ( intval( $current_product_price ) < intval( $product->productPrice ) ) {
					update_post_meta( $existing_wc_product->ID, '_regular_price', $product->productPrice );
					update_post_meta( $existing_wc_product->ID, '_price', $product->productPrice );
					$update_product_wc_status = array(
						'post_title'  => $product->productName,
						'post_status' => 'draft',
					);
					wp_update_post( $update_product_wc_status );
				}
				update_post_meta( $existing_wc_product->ID, '_ta_product_price', $product->productPrice );
				update_post_meta( $existing_wc_product->ID, '_ta_how_to_use', $product->howToUse );
			}
		} else if ($existing_wc_product_variations) {
			$existing_variant_name = "$product->productName [[$product->prodID]]";
			$existing_posts[] = $existing_variant_name;
			foreach($existing_wc_product_variations as $existing_wc_product_variation) {
				$existing_variation = wc_get_product( $existing_wc_product_variation->ID );
				update_post_meta($existing_variation->get_parent_id(), "taager_product", 1);
				$current_product_price = get_post_meta( $existing_wc_product_variation->ID, '_regular_price', true );
				$update_product_wc_status = array(
					'ID' => $existing_variation->get_parent_id(),
				);
				if ( intval( $current_product_price ) < intval( $product->productPrice ) ) {
					update_post_meta( $existing_wc_product->ID, '_regular_price', $product->productPrice );
					update_post_meta( $existing_wc_product->ID, '_price', $product->productPrice );
					update_post_meta( $existing_wc_product->ID, '_ta_product_profit', $product->productProfit );
					
					$update_product_wc_status['post_title']  = $product->productName;
					$update_product_wc_status['post_status'] = 'draft';
				}
				$update_product_wc_status['post_excerpt'] = $product->productDescription;
				$update_product_wc_status['post_content'] = $product->specifications;
				wp_update_post( $update_product_wc_status );
				update_post_meta($existing_wc_product_variation->ID, "_ta_how_to_use", $product->howToUse);
				update_post_meta($existing_wc_product_variation->ID, "taager_product", 1);
				$stock_status = $product->isProductAvailableToSell? 'instock' : 'outofstock';
				$existing_variation->set_stock_status($stock_status);
				$existing_variation->save();
			}
		} else if ($product->isProductAvailableToSell && !empty($product->attributes)) {
			
			$parent_product = taager_get_parent_product($product);
			update_post_meta($parent_product->get_id(), "taager_product", 1);

			$variable_product_attributes = [];
			foreach ($product->attributes as $attribute) {
				$variable_product_attributes[] = taager_add_product_attribute($attribute, $parent_product);
			}
			$parent_product->set_attributes($variable_product_attributes);
			$parent_product->save();
			
			$variant_product_id = taager_create_product_variant($product, $parent_product, $data);
			if(empty($parent_product->get_default_attributes())) {
				$default_attributes = array();
				foreach ($product->attributes as $attribute) {
					$default_attributes[$attribute->type] = taager_get_attribute_name($attribute->value);
				}
				$parent_product->set_default_attributes($default_attributes);
			}
			$parent_product->save();
			$count_import_product++;
		} else if ($product->isProductAvailableToSell && empty($product->attributes)) {
			$table     = $wpdb->prefix . 'posts';
			$sql       = "INSERT INTO `$table` (`post_title`, `post_type`, `post_status`, `post_content`, `post_excerpt`, `post_date` ) VALUES ('%s', '%s', '%s', '%s', '%s', now());";
			$post_data = [ $product->productName, 'product', 'draft', $product->specifications, $product->productDescription ];
			$wpdb->prepare( $sql, $post_data );
			
			$wpdb->query( $wpdb->prepare( $sql, $post_data ) );

			$product_id = $wpdb->insert_id;

			// Assign product to a category
			$term_id = $data['category']->term_id;
			wp_set_object_terms( $product_id, $term_id, 'product_cat' );

			// Set product type
			wp_set_object_terms( $product_id, 'simple', 'product_type' );

			taager_set_product_thumbnail_and_gallery( $product, $product_id, $data );

			// if product was created/updated successfully
			if ( ! is_wp_error( $product_id ) ) {
				// Save meta data
				foreach ( $data['meta'] as $meta_key => $meta_value ) {
					update_post_meta( $product_id, $meta_key, $meta_value );
				}
				// meta data to identify that product come from Taager plugin
				update_post_meta( $product_id, 'taager_product', 1 );
			}
			$count_import_product++;
			update_post_meta( $product_id, '_ta_how_to_use', $product->howToUse );
		}
	}
	$response = [
		'import'         => true,
		'imported_count' => $count_import_product,
		'existing_count' => count( $existing_posts ),
		'existing_post'  => $existing_posts,
	];

	if($count_import_product + count( $existing_posts ) > 0) {
		$mixpanel_event_properties['Imported Products Count'] = $count_import_product;
		$mixpanel_event_properties['Existing Products Count'] = count( $existing_posts );
		$mixpanel_event_properties['Products Import Time Taken in Seconds'] = (time() - $api_request_time);
		ta_track_event('taager_WP_import_products_success', $mixpanel_event_properties);
	}	else {
		update_option('ta_API_data', $api_response);
		if(isset($products_api_response->message)) {
			$mixpanel_event_properties['Failure reason'] = $products_api_response->message;
		} elseif (isset($products_api_response->data) && count($products_api_response->data) == 0) {
			$mixpanel_event_properties['Failure reason'] = 'No products found';
		} else {
			$mixpanel_event_properties['Failure reason'] = 'Unknown';
		}
		ta_track_event('taager_WP_import_products_fail', $mixpanel_event_properties);
	}

	return $response;
}

function taager_set_product_thumbnail_and_gallery( $product, $product_id, $data ) {
	
	// Set product feature image
	$featured_image_url = $product->productPicture;
	
	if( class_exists( 'Featured_Image_By_URL' ) && is_multisite() ) {
	
		fn_taager_product_thumbnail($product_id, $featured_image_url);
		
		// Set gallery image
		$cs_pro_gallery = $data['gallery_images'];
		fn_taager_product_gallery($product_id, $cs_pro_gallery);
		
	} else {
		
		taager_attach_product_thumbnail( $product_id, $featured_image_url, 1 );
		
		$gallery_img_list = [];
		foreach ( $data['gallery_images'] as $image_url ) {
			if ( $image_url == '' ) {
				continue;
			}

			taager_attach_product_thumbnail( $product_id, $image_url, 0 );
		}
	}
}

function taager_get_parent_product($product) {
	$posts = get_posts(
		array(
			'posts_per_page' => 1,
			'post_type'      => 'product',
			'post_status' => array( 'publish', 'draft' ),
			'title'       => $product->productName,
		)
	);
	$id = '';
	if(!empty($posts)) {
		$id = $posts[0]->ID;
	} else {
		$id = taager_create_parent_product($product);
	}
	$product_post = array(
		'ID'           => $id,
		'post_excerpt' => $product->productDescription,
		'post_content' => $product->specifications,
	);
	wp_update_post($product_post);
	update_post_meta($id, '_ta_how_to_use', $product->howToUse);
	return wc_get_product( $id );
}

function taager_create_parent_product($product) {
	$variable_product_parent = new WC_Product_Variable();
	$variable_product_parent->set_name($product->productName);
	$variable_product_parent->set_status('draft');

	if(is_object( $product->category )) {
		$category_term       = get_term_by( 'name', $product->category->text, 'product_cat' );
	} else {
		$category_term = get_term_by( 'name', ALL_PRODUCTS_CATEGORY, 'product_cat' );
	}
	$variable_product_parent->set_category_ids(array( intval($category_term->term_id)));

	return $variable_product_parent->save();
}

function taager_add_product_attribute ($attribute, $parent_product) {
	
	$current_attributes = $parent_product->get_attributes('edit');
	foreach($current_attributes as $current_attribute) {
		if($current_attribute->get_name() === $attribute->type) {
			$attribute_options = $current_attribute->get_options();
			if(!in_array(taager_get_attribute_name($attribute->value), $attribute_options)) {
				$attribute_options[] = taager_get_attribute_name($attribute->value);
			}
			return taager_create_new_attribute($attribute->type, $attribute_options);
		}
	}

	return taager_create_new_attribute($attribute->type, array(taager_get_attribute_name($attribute->value)));
}

function taager_create_new_attribute($attribute_name, $attribute_options) {
	$createdAttribute = new WC_Product_Attribute();
	$createdAttribute->set_id(0);
	$createdAttribute->set_name($attribute_name);
	$createdAttribute->set_options($attribute_options);
	$createdAttribute->set_visible(true);
	$createdAttribute->set_variation(true);
	return $createdAttribute;
}

function taager_create_product_variant($product, $parent_product, $data) {
	$variable_product_variant = new WC_Product_Variation();
	$variable_product_variant->set_parent_id($parent_product->get_id());
	$variable_product_variant->set_sku($product->prodID);
	$variable_product_variant->set_price($product->productPrice);
	$variable_product_variant->set_regular_price($product->productPrice);
	$variable_product_variant->set_stock_status(($product->isProductAvailableToSell)? 'instock': 'outofstock');
	
	$variable_product_attributes = [];
	foreach ($product->attributes as $attribute) {
		$variable_product_attributes[$attribute->type] = taager_get_attribute_name($attribute->value);
	}
	$variable_product_variant->set_attributes($variable_product_attributes);
	
	$variable_product_variant->save();
	$variant_product_id = get_posts(array(
    'post_type'  => 'product_variation',
    'meta_query' => array(
        array(
            'key'   => '_sku',
            'value' => $product->prodID,
        )
	)))[0]->ID;

	update_post_meta($variant_product_id, "_ta_product_price", $product->productPrice);
	update_post_meta($variant_product_id, "_ta_how_to_use", $product->howToUse);
	update_post_meta($variant_product_id, "_regular_price", $product->productPrice);
	update_post_meta($variant_product_id, "_ta_product_profit", $product->productProfit);
	update_post_meta($variant_product_id, "taager_product", 1);

	taager_set_product_thumbnail_and_gallery( $product, $variant_product_id, $data );
	
	if(!$parent_product->get_image_id()) {
		$product_image_id = get_post_meta( $variant_product_id, "_thumbnail_id" , true );
		$parent_product->set_image_id($product_image_id);
		
		$parent_product_image_gallery = get_post_meta( $variant_product_id, "_product_image_gallery" , true );
		update_post_meta($parent_product->get_id(), '_product_image_gallery', $parent_product_image_gallery);
		$parent_product->save();
	}

}


/**
 * Generate product metadata
 */
function taager_generate_product_data( $product ) {
	if(is_object( $product->category )) {
		$category       = get_term_by( 'name', $product->category->text, 'product_cat' );
	} else {
		$category = get_term_by( 'name', ALL_PRODUCTS_CATEGORY, 'product_cat' );
	}
	$extra_images = array(
		$product->extraImage1,
		$product->extraImage2,
		$product->extraImage3,
		$product->extraImage4,
		$product->extraImage5,
		$product->extraImage6,
	);
	if(!is_array($product->additionalMedia)) {
		$product->additionalMedia = array();
	}
	$gallery_images = array_merge($extra_images, $product->additionalMedia);
	$gallery_images = array_filter($gallery_images, function ($media) {
		return !str_ends_with($media, '.mp4');
	});

	if ( $product->isProductAvailableToSell ) {
		$stock_status = 'instock';
	} else {
		$stock_status = 'outofstock';
	}

	$data = array(
		'category'       => $category,
		'gallery_images' => $gallery_images,
		'meta'           => array(
			'_sku'               => $product->prodID,
			'_price'             => $product->productPrice,
			'_regular_price'     => $product->productPrice,
			'_stock_status'      => $stock_status,
			'_ta_product_profit' => $product->productProfit,
			'_ta_product_price'  => $product->productPrice,
		),
	);

	return $data;
}

/**
 * Get product by sku
 */
function taager_get_posts_by_sku( $sku, $post_type ) {
	$posts = get_posts(
		array(
			'posts_per_page' => -1,
			'post_type'      => $post_type,
			'post_status' => array( 'publish', 'draft' ),
			'meta_key'       => '_sku',
			'meta_value'     => $sku,
		)
	);

	return $posts ?: null;
}

//Add taager image as featured image
function fn_taager_product_thumbnail ($post_id, $knawatfibu_url) {
	
	$image_meta_url = '_knawatfibu_url';
	$image_meta_alt = '_knawatfibu_alt';
	
	if( isset( $knawatfibu_url ) ){
		global $knawatfibu;
		// Update Featured Image URL
		$image_url = isset( $knawatfibu_url ) ? esc_url( $knawatfibu_url ) : '';
		
		if ( $image_url != '' ){
			if( get_post_type( $post_id ) == 'product' || get_post_type( $post_id ) == 'product_variation' ){
				$img_url = get_post_meta( $post_id, $image_meta_url , true );
				if( is_array( $img_url ) && isset( $img_url['img_url'] ) && $image_url == $img_url['img_url'] ){
						$image_url = array(
							'img_url' => $image_url,
							'width'	  => $img_url['width'],
							'height'  => $img_url['height']
						);
				}else{
					$imagesize = @getimagesize( $image_url );
					$image_url = array(
						'img_url' => $image_url,
						'width'	  => isset( $imagesize[0] ) ? $imagesize[0] : '',
						'height'  => isset( $imagesize[1] ) ? $imagesize[1] : ''
					);
				}
			}
			update_post_meta( $post_id, $image_meta_url, $image_url );
		}else{
			delete_post_meta( $post_id, $image_meta_url );
			delete_post_meta( $post_id, $image_meta_alt );
		}
	} 
}

//Add taager gallery as featured gallery
function fn_taager_product_gallery($post_id, $knawatfibu_wcgallary) {
		
	global $knawatfibu;
	$gallery_key = '_knawatfibu_wcgallary';
	
	$old_images = $knawatfibu->common->knawatfibu_get_wcgallary_meta( $post_id );
	if( !empty( $old_images ) ){
		foreach ($old_images as $key => $value) {
			$old_images[$value['url']] = $value;
		}
	}

	$gallary_images = array();
	if( !empty( $knawatfibu_wcgallary ) ){
		foreach ($knawatfibu_wcgallary as $knawatfibu_gallary ) {
			if( isset( $knawatfibu_gallary ) && $knawatfibu_gallary != '' ){
				$gallary_image = array();
				$gallary_image['url'] = $knawatfibu_gallary;

				if( isset( $old_images[$gallary_image['url']]['width'] ) && $old_images[$gallary_image['url']]['width'] != '' ){
					$gallary_image['width'] = isset( $old_images[$gallary_image['url']]['width'] ) ? $old_images[$gallary_image['url']]['width'] : '';
					$gallary_image['height'] = isset( $old_images[$gallary_image['url']]['height'] ) ? $old_images[$gallary_image['url']]['height'] : '';

				}else{
					$imagesizes = @getimagesize( $knawatfibu_gallary );
					$gallary_image['width'] = isset( $imagesizes[0] ) ? $imagesizes[0] : '';
					$gallary_image['height'] = isset( $imagesizes[1] ) ? $imagesizes[1] : '';
				}

				$gallary_images[] = $gallary_image;
			}
		}
	}

	if( !empty( $gallary_images ) ){
		update_post_meta( $post_id, $gallery_key, $gallary_images );
	}else{
		delete_post_meta( $post_id, $gallery_key );
	}	
	
}

/**
 * Attach images to product (feature / gallery)
 */
function taager_attach_product_thumbnail( $product_id, $url, $isPostThumbnail ) {
	
	global $wpdb;
	
	// If allow_url_fopen is enable in php.ini then use this
	$image_url        = $url;
	$url_array        = explode( '/', $url );
	$image_name       = $url_array[ count( $url_array ) - 1 ];
	$image_data       = file_get_contents( $image_url ); // Get image data
	$upload_dir       = wp_upload_dir(); // Set upload folder
	$unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name ); // Generate unique name
	$filename         = basename( $unique_file_name ); // Create image file name

	// Check folder permission and define file location
	if ( wp_mkdir_p( $upload_dir['path'] ) ) {
		$file = $upload_dir['path'] . '/' . $filename;
	} else {
		$file = $upload_dir['basedir'] . '/' . $filename;
	}

	// Create the image file on the server
	$upload_result = file_put_contents( $file, $image_data );

	// Check image file type
	$wp_filetype = wp_check_filetype( $filename, null );

	// Mixpanel tracking for image upload
	if($isPostThumbnail) {
		ta_track_event( 'taager_WP_thumbnail_image_upload', array(
			'image_url' => $image_url,
			'url_array' => $url_array,
			'image_name' => $image_name,
			'image_data_length' => strlen($image_data),
			'upload_dir' => $upload_dir,
			'unique_file_name' => $unique_file_name,
			'filename' => $filename,
			'upload_result' => $upload_result,
			'php_version' => phpversion(),
			'disk_free_space' => disk_free_space("/"),
			'disk_total_space' => disk_total_space("/"),
			'file_path' => $file,
			'file_permissions' => substr(decoct(fileperms($file)), -4),
			'file_size' => filesize($file),
		));
	}

	// Set attachment data
	$attachment = array(
		'post_mime_type' => $wp_filetype['type'],
		'post_title'     => sanitize_file_name( $filename ),
		'post_content'   => '',
		'post_status'    => 'inherit',
	);
	
	$image_query = "SELECT * FROM {$wpdb->posts} WHERE post_title LIKE '%$image_name%'";
	$image_id = intval($wpdb->get_var($image_query));
	
	if($image_id != 0) {
		
		$attach_id = $image_id;
		
	} else {
		// Create the attachment
		$attach_id = wp_insert_attachment( $attachment, $file, $product_id );
	
		// Include image.php
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		// Define attachment metadata
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file );

		// Assign metadata to attachment
		wp_update_attachment_metadata( $attach_id, $attach_data );

	}
	
	// Assign featured image to product
	if ( $isPostThumbnail == 1 ) {
		set_post_thumbnail( $product_id, $attach_id );
	}

	// Assign gallery images to product
	if ( $isPostThumbnail == 0 ) {
		$attach_id_array  = get_post_meta( $product_id, '_product_image_gallery', true );
		$attach_id_array .= ',' . $attach_id;
		update_post_meta( $product_id, '_product_image_gallery', $attach_id_array );
	}
}
