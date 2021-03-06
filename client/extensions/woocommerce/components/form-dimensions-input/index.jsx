/** @format */

/**
 * External dependencies
 */

import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import classNames from 'classnames';
import { localize } from 'i18n-calypso';
import { noop } from 'lodash';
import { TextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { getSelectedSiteWithFallback } from 'woocommerce/state/sites/selectors';
import { getDimensionsUnitSetting } from 'woocommerce/state/sites/settings/products/selectors';
import { fetchSettingsProducts } from 'woocommerce/state/sites/settings/products/actions';
import TextControlWithAffixes from 'components/forms/text-control-with-affixes';

class FormDimensionsInput extends Component {
	static propTypes = {
		className: PropTypes.string,
		dimensions: PropTypes.shape( {
			width: PropTypes.string,
			height: PropTypes.string,
			length: PropTypes.string,
		} ),
		dimensionsUnit: PropTypes.string,
		onChange: PropTypes.func.isRequired,
		noWrap: PropTypes.bool,
	};

	static defaultProps = {
		value: '',
		className: '',
		onChange: noop,
		noWrap: false,
	};

	componentDidMount() {
		const { siteId } = this.props;

		if ( siteId ) {
			this.props.fetchSettingsProducts( siteId );
		}
	}

	UNSAFE_componentWillReceiveProps( newProps ) {
		if ( newProps.siteId !== this.props.siteId ) {
			this.props.fetchSettingsProducts( newProps.siteId );
		}
	}

	render() {
		const { className, noWrap, dimensions, onChange, translate, dimensionsUnit } = this.props;
		const classes = classNames( 'form-dimensions-input', className, { 'no-wrap': noWrap } );

		return (
			<div className={ classes }>
				<TextControl
					name="length"
					placeholder={ translate( 'L', { comment: 'Length placeholder for dimensions input' } ) }
					type="number"
					value={ ( dimensions && dimensions.length ) || '' }
					onChange={ value => onChange( value, 'length' ) }
					className="form-dimensions-input__length"
				/>
				<TextControl
					name="width"
					placeholder={ translate( 'W', { comment: 'Width placeholder for dimensions input' } ) }
					type="number"
					value={ ( dimensions && dimensions.width ) || '' }
					onChange={ value => onChange( value, 'width' ) }
					className="form-dimensions-input__width"
				/>
				<TextControlWithAffixes
					name="height"
					placeholder={ translate( 'H', { comment: 'Height placeholder for dimensions input' } ) }
					suffix={ dimensionsUnit }
					type="number"
					value={ ( dimensions && dimensions.height ) || '' }
					onChange={ value => onChange( value, 'height' ) }
					className="form-dimensions-input__height"
				/>
			</div>
		);
	}
}

function mapStateToProps( state, { dimensionsUnit } ) {
	const site = getSelectedSiteWithFallback( state );
	if ( ! dimensionsUnit ) {
		const dimensionsUnitSetting = site && getDimensionsUnitSetting( state, site.ID );
		dimensionsUnit = ( dimensionsUnitSetting && dimensionsUnitSetting.value ) || 'in';
	}

	return {
		siteId: site && site.ID,
		dimensionsUnit,
	};
}

function mapDispatchToProps( dispatch ) {
	return bindActionCreators(
		{
			fetchSettingsProducts,
		},
		dispatch
	);
}

export default connect(
	mapStateToProps,
	mapDispatchToProps
)( localize( FormDimensionsInput ) );
