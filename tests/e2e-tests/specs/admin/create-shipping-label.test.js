
/**
 * @format
 */

/**
 * Internal dependencies
 */
import { clickReactButton } from '../../utils/index';
import { StoreOwnerFlow } from '../../utils/flows';
import db from '../../fixtures/db';

describe( 'Create shipping label', () => {
	beforeAll( async () => {
		page.setDefaultTimeout( 0 );
		page.setDefaultNavigationTimeout( 0 );
		page.on( "dialog", ( dialog ) => {
  	  	  	console.log( `accepting dialog: ${ dialog.message() }` );
  	  	  	dialog.accept();
		} );
	} );

	it( 'should create a new shipping label', async () => {
		await db.loadFixtures( async ( models ) => {
			await StoreOwnerFlow.login();
			await StoreOwnerFlow.openExistingOrderPage( models.wp_posts[1].ID );

			// Click on Create shipping label button
			await clickReactButton( '.shipping-label__new-label-button' );
			await page.waitForSelector( '.dialog__content' );

			const isOriginAddressReady = await page.waitForSelector( '.is-success', { text: 'NEW YORK, NY  10075' } );
			if ( !isOriginAddressReady ) {
				// Work on origin address section
				await page.waitForSelector( '.address-step__actions' );

				// Fill in origin address details
				await expect( page ).toFill( '#origin_address', '1480 York Ave' );
				await expect( page ).toFill( '#origin_city', 'New York' );
				await expect( page ).toSelect( '#origin_state', 'New York' );
				await expect( page ).toFill( '#origin_postcode', '10075' );

				// Verify address
				await expect( page ).toClick( '.wp-core-ui.wp-admin .wcc-root .address-step__actions .form-button:first-child' );
				await page.waitForSelector( '.is-success', { text: 'NEW YORK, NY  10075' } );
			}

			// Use selected address
			const isDestinationAddressReady = await page.$( '.is-success', { text: 'San Francisco, CA  94107' } );

			if ( !isDestinationAddressReady ) {
				await page.waitForSelector( '.address-step__suggestion-container .address-step__suggestion:first-child' );
				await expect( page ).toClick( '.address-step__suggestion-container .address-step__suggestion:first-child' );
				await page.waitForSelector( '.button.is-primary', { text: 'Use selected address' } );
				await expect( page ).toClick( '.button.is-primary', { text: 'Use selected address' } );
				await page.waitForSelector( '.is-success', { text: 'San Francisco, CA  94107' } );
			}

			const selectAPackageType = await page.$( '.packages-step__no-packages a', { text: 'Select a package type' } );
			if ( selectAPackageType ) {
				await expect( page ).toClick( 'Select a package type' );
				await selectAPackageType.click();
				await expect( page ).toFill( '#weight_default_box', '1' );

				await page.waitForSelector( '.packages__add-edit-dialog' );

				await expect( page ).toFill( '.packages__add-edit-dialog #name', 'My Package' );
				await expect( page ).toFill( '.packages__add-edit-dialog #length', '1' );
				await expect( page ).toFill( '.packages__add-edit-dialog #width', '1' );
				await expect( page ).toFill( '.packages__add-edit-dialog #height', '1' );
				await expect( page ).toFill( '.packages__add-edit-dialog #box_weight', '1' );

				await expect( page ).toClick( '.button.is-primary', { text: 'Add package' } );

			}

			await expect( page ).toClick( '.button.is-primary', { text: 'Use these packages' } );
			await page.waitForSelector( '.is-success', { text: '1 item in 1 package: 2 kg total' } );

			await page.waitForSelector( '#inspector-radio-control-0-0' );
			await expect( page ).toClick( '#inspector-radio-control-0-0' );

			await expect( page ).toClick( '.button.is-primary', { text: 'Buy shipping labels' } );

			await page.waitForSelector( '.notice.is-success .notice__text', { text: 'Your shipping label was purchased successfully' } )
		} );

	} );
} );
