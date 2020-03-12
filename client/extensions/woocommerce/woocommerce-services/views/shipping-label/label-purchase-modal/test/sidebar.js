/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { expect } from 'chai';
import { configure, mount, shallow } from 'enzyme';
import Adapter from 'enzyme-adapter-react-16'
import { CheckboxControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Dropdown from 'woocommerce/woocommerce-services/components/dropdown';
import { Sidebar } from '../sidebar.js';

configure({ adapter: new Adapter() });

function createSidebarWrapper( { status = 'processing', fulfillOrder = true, emailDetails = true } ) {
	const props = {
		orderId: 1000,
		siteId: 10,
		translate: (text) => text,
		order: {
			status,
		},
		paperSize: 'letter',
		errors: {},
		form: { origin: { values: { country: 'US' } } },
		updatePaperSize: () => true,
		fulfillOrder: fulfillOrder,
		emailDetails: emailDetails,
		hasLabelsPaymentMethod: true,
		setFulfillOrderOption: jest.fn(),
	};

	const wrapper = shallow( <Sidebar { ...props } /> );

	return {
		wrapper,
		props
	}
}

describe( 'Sidebar', () => {
	describe( 'for defaulte order state', () => {
		const { wrapper, props } = createSidebarWrapper( {} );
		const renderedCheckboxControl = wrapper.find( CheckboxControl )

		it( 'renders a checkbox control', function () {
			expect( renderedCheckboxControl ).to.have.lengthOf( 1 );
		} );

		it( 'Has the Correct Label', function () {
			expect( renderedCheckboxControl.props().label ).to.equal( 'Mark this order as complete and notify the customer' );
		} );

		it( 'Unchecked checkbox disables fulfilling the order', function() {
			renderedCheckboxControl.props().onChange(false)
			expect( props.setFulfillOrderOption.mock.calls ).to.have.lengthOf( 1 );
			expect( props.setFulfillOrderOption.mock.calls[0][2] ).to.equal( false );
		} );

		it( 'it is checked', function() {
			expect( renderedCheckboxControl.props().checked ).to.equal( true );
		} );

		it( 'Has paper size dropdown', function () {
			const paperDropdown = wrapper.find( Dropdown )
			const paperDropdownProps = paperDropdown.props();
			expect( paperDropdown ).to.have.lengthOf( 1 );
			expect( paperDropdownProps.title ).to.equal( 'Paper size' );
			expect( paperDropdownProps.value ).to.equal( props.paperSize );
		} );

	} );
	describe( 'for completed orders', () => {
		const { wrapper, props } = createSidebarWrapper( { status: 'completed', fulfillOrder: false, emailDetails: false } );
		const renderedCheckboxControl = wrapper.find( CheckboxControl )

		it( 'Has a the Correct Label', function () {
			expect( renderedCheckboxControl.props().label ).to.equal( 'Notify the customer with shipment details' );
		} );

		it( 'it is not checked', function() {
			expect( renderedCheckboxControl.props().checked ).to.equal( false );
		} );
	} );
	describe( 'for no payment method', () => {
		const { wrapper, props } = createSidebarWrapper( { hasLabelsPaymentMethod: false } );
		const renderedCheckboxControl = wrapper.find( CheckboxControl )

		it( 'Has a the Correct Label', function () {
			expect( wrapper.find( Dropdown ) ).to.have.lengthOf( 1 );
		} );
	} );
} );
