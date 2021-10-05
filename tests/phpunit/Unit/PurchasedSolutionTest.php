<?php
declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Tests\Unit;

use PixelgradeLT\Retailer\PurchasedSolution;

class PurchasedSolutionTest extends TestCase {

	public function test_init_with_array() {
		$props = [
			'id'             => 123,
			'status'         => 'ready',
			'solution_id'    => 45,
			'user_id'        => 657,
			'order_id'       => 1263,
			'order_item_id'  => 7234,
			'composition_id' => 1632,
			'date_created'   => '-1 week',
			'date_modified'  => 'now',
		];

		// For some reason this instantiation results in a seg fault.
		$purchasedSolution = new PurchasedSolution( $props );

		$this->assertSame( $props['id'], $purchasedSolution->id );
		$this->assertSame( $props['status'], $purchasedSolution->status );
		$this->assertSame( $props['solution_id'], $purchasedSolution->solution_id );
		$this->assertSame( $props['user_id'], $purchasedSolution->user_id );
		$this->assertSame( $props['order_id'], $purchasedSolution->order_id );
		$this->assertSame( $props['order_item_id'], $purchasedSolution->order_item_id );
		$this->assertSame( $props['composition_id'], $purchasedSolution->composition_id );
		$this->assertSame( $props['date_created'], $purchasedSolution->date_created );
		$this->assertSame( $props['date_modified'], $purchasedSolution->date_modified );
	}
}
