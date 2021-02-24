/** @format */

/**
 * External dependencies
 */
import React from 'react'
import { mount } from 'enzyme'
import * as api from 'api'
import { act } from 'react-dom/test-utils'
import { Provider } from 'react-redux'
import configureMockStore from 'redux-mock-store'
import sinon from 'sinon'
import thunk from 'redux-thunk';

/**
 * Internal dependencies
 */
import HealthView from '../health'

const apiPostSpy = jest.spyOn(api, 'post').mockImplementation(() =>
	Promise.resolve({})
)

const defaultHealthStoreValues = {
	woocommerce: {
		state: 'success',
		message: 'WooCommerce 5.0.0 is configured correctly'
	},
	jetpack: {
		state: 'success',
		message: 'Jetpack 9.4 is connected and working correctly',
	},
	woocommerce_services: {
		timestamp: 1614185820,
		has_service_schemas: true,
		error_threshold: 259200,
		warning_threshold: 86400,
	}
}

const mockStore = configureMockStore([thunk])
const Wrapper = ({ children, healthStoreOverrides }) => (
	<Provider store={mockStore({
		status: {
			health_items: {
				...defaultHealthStoreValues,
				...healthStoreOverrides,
			}
		},
	})}>
		{children}
	</Provider>
)

describe('Health View', () => {
	let clock
	beforeAll(() => {
		clock = sinon.useFakeTimers(new Date('2021-02-24T11:00:00'))
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	afterAll(() => {
		clock.restore()
		jest.restoreAllMocks()
	})

	it('should display the default state', () => {
		const wrapper = mount(
			<Wrapper>
				<HealthView/>
			</Wrapper>
		)

		const sections = wrapper.find('fieldset.form-fieldset')

		expect(sections).toHaveLength(3)

		const wooCommerceSection = sections.at(0)
		const jetpackSection = sections.at(1)
		const wcsSection = sections.at(2)

		expect(wooCommerceSection.text()).toContain('WooCommerce 5.0.0 is configured correctly')
		expect(jetpackSection.text()).toContain('Jetpack 9.4 is connected and working correctly')
		expect(wcsSection.text()).toContain('Service data is up-to-date')
		expect(wcsSection.text()).toContain('Last updated 3 minutes ago')
	})

	it('should display an error when there are no service schema', () => {
		const wrapper = mount(
			<Wrapper healthStoreOverrides={{
				woocommerce_services: {
					...defaultHealthStoreValues.woocommerce_services,
					has_service_schemas: false,
				}
			}}>
				<HealthView/>
			</Wrapper>
		)

		const wcsSection = wrapper.find('fieldset.form-fieldset').at(2)

		expect(wcsSection.text()).toContain('No service data available')
		expect(wcsSection.text()).not.toContain('Last updated')
	})

	it('should display a warning when there is no timestamp', () => {
		const wrapper = mount(
			<Wrapper healthStoreOverrides={{
				woocommerce_services: {
					...defaultHealthStoreValues.woocommerce_services,
					timestamp: 0,
				}
			}}>
				<HealthView/>
			</Wrapper>
		)

		const wcsSection = wrapper.find('fieldset.form-fieldset').at(2)

		expect(wcsSection.text()).toContain('Service data found, but may be out of date')
		expect(wcsSection.text()).not.toContain('Last updated')
	})

	it('should display an error when the data is more than 3 days old', () => {
		const wrapper = mount(
			<Wrapper healthStoreOverrides={{
				woocommerce_services: {
					...defaultHealthStoreValues.woocommerce_services,
					timestamp: 151418582,
				}
			}}>
				<HealthView/>
			</Wrapper>
		)

		const wcsSection = wrapper.find('fieldset.form-fieldset').at(2)

		expect(wcsSection.text()).toContain('Service data was found, but is more than three days old')
		expect(wcsSection.text()).toContain('Last updated 46 years ago.')
	})

	it('should display a warning when the data is more than 1 day old but less than 3 days old', () => {
		const wrapper = mount(
			<Wrapper healthStoreOverrides={{
				woocommerce_services: {
					...defaultHealthStoreValues.woocommerce_services,
					timestamp: 1614013200,
				}
			}}>
				<HealthView/>
			</Wrapper>
		)

		const wcsSection = wrapper.find('fieldset.form-fieldset').at(2)

		expect(wcsSection.text()).toContain('Service data was found, but is more than one day old')
		expect(wcsSection.text()).toContain('Last updated 2 days ago.')
	})

	it('should call the API when the refresh button is clicked', async () => {
		const wrapper = mount(
			<Wrapper>
				<HealthView/>
			</Wrapper>
		)

		expect(apiPostSpy).not.toHaveBeenCalled()

		await act(async () => {
			wrapper.find( 'a' ).simulate('click');
		} );

		expect(apiPostSpy).toHaveBeenCalled()
	})
})
