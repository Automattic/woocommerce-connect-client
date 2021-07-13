/**
 * External dependencies
 */
import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { translate as __ } from 'i18n-calypso';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

/**
 * Internal dependencies
 */
import { getPaperSizes } from 'lib/pdf-label-utils';
import * as PrintTestLabelActions from './state/actions';
import ErrorNotice from 'components/error-notice';
import FormButton from 'wcs-client/components/forms/form-button';
import FormFieldset from 'components/forms/form-fieldset';
import FormSelect from 'wcs-client/components/forms/form-select';
import FormLegend from 'components/forms/form-legend';
import SettingsGroupCard from 'woocommerce/woocommerce-services/components/settings-group-card';

class PrintTestLabelView extends Component {
	static propTypes = {
		paperSize: PropTypes.string.isRequired,
		country: PropTypes.string.isRequired,
		printingInProgress: PropTypes.bool,
		error: PropTypes.string,
		updatePaperSize: PropTypes.func.isRequired,
		print: PropTypes.func.isRequired,
	};

	onPaperSizeChange = ( event ) => this.props.updatePaperSize( event.target.value );

	render() {
		const { paperSize, country, printingInProgress, error, print } = this.props;
		const paperSizes = getPaperSizes( country );

		return (
			<SettingsGroupCard heading={ __( 'Print' ) } >
				{ error && <ErrorNotice>{ error }</ErrorNotice> }
				<FormLegend>{ __( 'Having trouble configuring your printer?' ) }</FormLegend>
				<p>{ __( 'You can run a test print for shipping labels by selecting the paper size, then print.' ) }</p>
				<FormFieldset>
					<FormLegend>{ __( 'Paper size' ) }</FormLegend>
					<div className="print-test-label__form-container">
						<FormSelect
							className="print-test-label__paper-size"
							value={ paperSize }
							onChange={ this.onPaperSizeChange } >
							{ Object.keys( paperSizes ).map( key =>
								<option key={ key } value={ key }>
									{ paperSizes[ key ] }
								</option>
							) }
						</FormSelect>
						<FormButton
							type="button"
							disabled={ Boolean( printingInProgress ) }
							onClick={ print }
							isPrimary={ false }>
							{ __( 'Print' ) }
						</FormButton>
					</div>
				</FormFieldset>
			</SettingsGroupCard>
		);
	}
}

export default connect(
	( state ) => state,
	( dispatch ) => bindActionCreators( PrintTestLabelActions, dispatch )
)( PrintTestLabelView );
