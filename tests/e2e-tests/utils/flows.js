const baseUrl = process.env.WP_BASE_URL;
const adminUserName = process.env.WP_ADMIN_USER_NAME;
const adminUserPassword = process.env.WP_ADMIN_USER_PW;

const WP_ADMIN_LOGIN = baseUrl + '/wp-login.php';
const WP_ADMIN_PLUGINS_PAGE = baseUrl + '/wp-admin/plugins.php';
const WP_ADMIN_ORDERS_PAGE = baseUrl + '/wp-admin/edit.php?post_type=shop_order';
const WP_ADMIN_EDIT_ORDER_PAGE = function( orderId ) {
	return baseUrl + `/wp-admin/post.php?post=${ orderId }&action=edit`;
};
const WP_ADMIN_NEW_PRODUCT = baseUrl + '/wp-admin/post-new.php?post_type=product';
const WP_ADMIN_WC_SETTINGS = baseUrl + '/wp-admin/admin.php?page=wc-settings&tab=';
const WP_ADMIN_WC_STATUS = baseUrl + '/wp-admin/admin.php?page=wc-status&tab=';

const SHOP_PAGE = baseUrl + '/shop/';
const SHOP_CHECKOUT_PAGE = baseUrl + '/checkout/';

const CustomerFlow = {
	addToCartFromShopPage: async ( productTitle ) => {
		const addToCartXPath = `//li[contains(@class, "type-product") and a/h2[contains(text(), "${ productTitle }")]]` +
			'//a[contains(@class, "add_to_cart_button") and contains(@class, "ajax_add_to_cart")';

		const [ addToCartButton ] = await page.$x( addToCartXPath + ']' );
		addToCartButton.click();

		await page.waitFor( addToCartXPath + ' and contains(@class, "added")]' );
	},

	goToCheckout: async () => {
		await page.goto( SHOP_CHECKOUT_PAGE );
	},

	goToShop: async () => {
		await page.goto(SHOP_PAGE);
	},

	placeOrder: async () => {
		await Promise.all( [
			expect( page ).toClick( '#place_order' ),
			page.waitForNavigation( { waitUntil: 'networkidle0' } ),
		] );
	},

	productIsInCheckout: async ( productTitle, quantity, total ) => {
		await expect( page ).toMatchElement( '.cart_item .product-name', { text: productTitle } );
		await expect( page ).toMatchElement( '.cart_item .product-quantity', { text: `× ${quantity}` } );
		await expect( page ).toMatchElement( '.cart_item .product-total', { text: `\$${ total }` } );
	},
};

const StoreOwnerFlow = {
    login: async () => {
        await page.goto( WP_ADMIN_LOGIN );

		await expect( page.title() ).resolves.toMatch( 'Log In' );

		await page.type( '#user_login', adminUserName );
		await page.type( '#user_pass', adminUserPassword );

		await Promise.all( [
			page.click( 'input[type=submit]' ),
			page.waitForNavigation(),
		] );
	},

	logout: async () => {
		await page.goto( baseUrl + '/wp-login.php?action=logout' );

		await expect( page ).toMatch( 'You are attempting to log out' );

		await Promise.all( [
			page.waitForNavigation( { waitUntil: 'networkidle0' } ),
			page.click( 'a' ),
		] );
	},

	openExistingOrderPage: async ( orderId ) => {
		await page.goto( WP_ADMIN_EDIT_ORDER_PAGE( orderId ) );
	},

	openNewProduct: async () => {
		await page.goto( WP_ADMIN_NEW_PRODUCT );
	},

	openPluginsPage: async () => {
		await page.goto( WP_ADMIN_PLUGINS_PAGE );
	},

	openSettings: async ( tab, section = null ) => {
		let settingsUrl = WP_ADMIN_WC_SETTINGS + tab;

		if ( section ) {
			settingsUrl += `&section=${ section }`;
		}

		await page.goto( settingsUrl );
	},

	openStatus: async ( tab, section = null ) => {
		let statusUrl = WP_ADMIN_WC_STATUS + tab;

		if ( section ) {
			statusUrl += `&section=${ section }`;
		}

		await page.goto( statusUrl );
	},
}

module.exports = {
	CustomerFlow,
	StoreOwnerFlow
};
