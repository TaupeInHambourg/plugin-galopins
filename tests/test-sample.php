<?php

class SampleTest extends WP_UnitTestCase {

	/**
	 * Test si le plugin est bien activé
	 */
	public function test_plugin_activated() {
		$this->assertTrue( is_plugin_active( 'galopins-tools/galopins-tools.php' ) );
	}

	/**
	 * Test d'une fonction de votre plugin
	 */
	public function test_plugin_function() {
		// Exemple : si vous avez une fonction get_plugin_version()
		// $this->assertEquals( '1.0.0', get_plugin_version() );
		
		// Pour l'instant, test basique
		$this->assertTrue( true );
	}

	/**
	 * Test d'insertion de post
	 */
	public function test_post_creation() {
		$post_id = $this->factory->post->create( array(
			'post_title' => 'Test Post',
			'post_content' => 'Test content',
			'post_status' => 'publish'
		) );

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );
	}

	/**
	 * Test d'un shortcode (exemple)
	 */
	public function test_shortcode() {
		// Si vous avez un shortcode [galopins_test]
		// $output = do_shortcode( '[galopins_test]' );
		// $this->assertStringContainsString( 'expected_content', $output );
		
		$this->assertTrue( true );
	}
}

/**
 * Test des fonctions utilitaires
 */
class UtilityTest extends WP_UnitTestCase {

	public function test_sanitize_function() {
		// Exemple de test de fonction de sanitization
		// $result = your_sanitize_function( '<script>alert("xss")</script>test' );
		// $this->assertEquals( 'test', $result );
		
		$this->assertTrue( true );
	}

	public function test_validation_function() {
		// Test de validation
		// $this->assertTrue( your_validation_function( 'valid_data' ) );
		// $this->assertFalse( your_validation_function( 'invalid_data' ) );
		
		$this->assertTrue( true );
	}
}