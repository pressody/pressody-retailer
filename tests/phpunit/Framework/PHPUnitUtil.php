<?php
declare ( strict_types=1 );

namespace Pressody\Retailer\Tests\Framework;

use PHPUnit\TextUI\Command;
use PHPUnit\Util\Getopt;
use ReflectionClass;
use ReflectionMethod;

class PHPUnitUtil {
	/**
	 * Retrieve the current test suite.
	 *
	 * @return string
	 */
	public static function get_current_suite() {
		$suite = '';

		$options = self::get_phpunit_options();

		if ( ! empty( $options[1] ) ) {
			// File or directory.
			$source = $options[1][1] ?? $options[1][0];
			$source = str_replace( 'tests/phpunit', '', $source );

			if ( 0 === strpos( $source, '/Unit' ) ) {
				$suite = 'Unit';
			}
		}

		foreach ( $options[0] as $arg ) {
			if ( '--testsuite' === $arg[0] ) {
				$suite = $arg[1];
				break;
			}
		}

		return $suite;
	}

	/**
	 * Retrieve PHPUnit CLI arguments.
	 *
	 * @return array
	 */
	public static function get_phpunit_options() {
		$class    = new ReflectionClass( Command::class );
		$property = $class->getProperty( 'longOptions' );
		$property->setAccessible( true );

		$value        = $property->getValue( new Command() );
		$long_options = array_keys( $value );

		// In PHPUnit 8, Getopt::getopt is renamed to Getopt::parse, and in PHPUnit 9 the whole class is dropped for sebastian/cli-parser
		// @link https://github.com/sebastianbergmann/phpunit/commit/44cb2c424b5d0b46a20faa49146f32e3bef52083#diff-4936fc958b7ea691bb00730f69d2ff4a7c9dab9308e224c471441f3a153d6da9
		// Since WordPress Unit Tests currently support only PHPUnit 7+, we will delay the update.
		// @link https://core.trac.wordpress.org/ticket/46149
		return Getopt::getopt(
			$GLOBALS['argv'],
			'd:c:hv',
			$long_options
		);
	}

	/**
	 * Get a private method for testing/documentation purposes.
	 * How to use for MyClass->foo():
	 *      $cls = new MyClass();
	 *      $foo = PHPUnitUtil::getPrivateMethod($cls, 'foo');
	 *      $foo->invoke($cls, $...);
	 *
	 * @param object $obj  The instantiated instance of your class
	 * @param string $name The name of your private method
	 *
	 * @return ReflectionMethod The method you asked for
	 */
	public static function getPrivateMethod( object $obj, string $name ): ReflectionMethod {
		$class  = new ReflectionClass( $obj );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );

		return $method;
	}

	/**
	 * Get a protected method for testing/documentation purposes.
	 * How to use for MyClass->foo():
	 *      $cls = new MyClass();
	 *      $foo = PHPUnitUtil::getProtectedMethod($cls, 'foo');
	 *      $foo->invoke($cls, $...);
	 *
	 * @param object $obj  The instantiated instance of your class
	 * @param string $name The name of your protected method
	 *
	 * @return ReflectionMethod The method you asked for
	 */
	public static function getProtectedMethod( object $obj, string $name ): ReflectionMethod {
		return self::getPrivateMethod( $obj, $name );
	}

	/**
	 * Get a private property value for testing/documentation purposes.
	 * How to use for MyClass->foo:
	 *      $cls = new MyClass();
	 *      $foo = PHPUnitUtil::getPrivateProperty($cls, 'foo');
	 *
	 * @param object $obj  The instantiated instance of your class
	 * @param string $name The name of your private property
	 *
	 * @return mixed The value of the property you've asked for.
	 */
	public static function getPrivateProperty( object $obj, string $name ) {
		$class    = new ReflectionClass( $obj );
		$property = $class->getProperty( $name );
		$property->setAccessible( true );

		return $property->getValue( $obj );
	}

	/**
	 * Get a protected property value for testing/documentation purposes.
	 * How to use for MyClass->foo:
	 *      $cls = new MyClass();
	 *      $foo = PHPUnitUtil::getProtectedProperty($cls, 'foo');
	 *
	 * @param object $obj  The instantiated instance of your class
	 * @param string $name The name of your protected property
	 *
	 * @return mixed The value of the property you've asked for.
	 */
	public static function getProtectedProperty( object $obj, string $name ) {
		return self::getPrivateProperty( $obj, $name );
	}

	/**
	 * Get a private property value for testing/documentation purposes.
	 * How to use for MyClass->foo:
	 *      $cls = new MyClass();
	 *      $foo = PHPUnitUtil::setPrivateProperty($cls, 'foo', 'value');
	 *
	 * @param object $obj  The instantiated instance of your class
	 * @param string $name The name of your private property
	 */
	public static function setPrivateProperty( object $obj, string $name, $value ) {
		$class    = new ReflectionClass( $obj );
		$property = $class->getProperty( $name );
		$property->setAccessible( true );
		$property->setValue( $obj, $value );
	}

	/**
	 * Set a protected property value for testing/documentation purposes.
	 * How to use for MyClass->foo:
	 *      $cls = new MyClass();
	 *      $foo = PHPUnitUtil::setProtectedProperty($cls, 'foo', 'value');
	 *
	 * @param object $obj  The instantiated instance of your class
	 * @param string $name The name of your protected property
	 */
	public static function setProtectedProperty( object $obj, string $name, $value ) {
		self::setPrivateProperty( $obj, $name, $value );
	}
}
