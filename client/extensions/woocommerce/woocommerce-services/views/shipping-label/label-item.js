/** @format */

/**
 * External dependencies
 */
import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { localize } from 'i18n-calypso';
import Gridicon from 'gridicons';

/**
 * WordPress dependencies
 */
import { DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';
import { Fragment } from '@wordpress/element';

/**
 * Internal dependencies
 */
import RefundDialog from './label-refund-modal';
import ReprintDialog from './label-reprint-modal';
import DetailsDialog from './label-details-modal';
import TrackingLink from './tracking-link';
import {
	openRefundDialog,
	openReprintDialog,
	openDetailsDialog,
} from 'woocommerce/woocommerce-services/state/shipping-label/actions';

export class LabelItem extends Component {
	renderRefund = ( labelId, expired, carrierId, trackingId ) => {
		const { orderId, siteId, translate } = this.props;
		let info     = '';
		let disabled = false;

		if ( expired ) {
			info     = translate( 'Labels older than 30 days cannot be refunded.' );
			disabled = true;
		}
		else if ( 'usps' === carrierId && ! trackingId ) {
			info     = translate( 'USPS labels without tracking are not eligible for refund.' );
			disabled = true
		}

		const openDialog = () => {
			this.props.openRefundDialog( orderId, siteId, labelId );
		};

		return (
			<MenuItem
				disabled={ disabled }
				icon={ <Gridicon icon="refund" /> }
				info={ info }
				onClick={ openDialog }
			>
				{ translate( 'Request refund' ) }
			</MenuItem>
		);
	};

	renderReprint = ( labelId, expired ) => {
		const { orderId, siteId, translate } = this.props;
		let info = '';

		if ( expired ) {
			info = translate( 'Label images older than 180 days are deleted by our technology partners for general security and data privacy concerns.' );
		}

		const openDialog = () => {
			this.props.openReprintDialog( orderId, siteId, labelId );
		};

		return (
			<MenuItem
				disabled={ expired }
				icon={ <Gridicon icon="print" /> }
				info={ info }
				onClick={ openDialog }
			>
				{ translate( 'Reprint shipping label' ) }
			</MenuItem>
		);
	};

	renderLabelDetails = labelId => {
		const { orderId, siteId, translate } = this.props;

		const openDialog = () => {
			this.props.openDetailsDialog( orderId, siteId, labelId );
		};

		return (
			<MenuItem onClick={ openDialog } icon={ <Gridicon icon="info-outline" /> }>
				{ translate( 'View details' ) }
			</MenuItem>
		);
	};

	renderPickup = carrierId => {
		const { translate } = this.props;
		const pickup_urls = {
			'usps': 'https://tools.usps.com/schedule-pickup-steps.htm',
			'fedex': 'https://www.fedex.com/en-us/shipping/schedule-manage-pickups.html',
			'ups': 'https://wwwapps.ups.com/pickup/request',
			'dhlexpress': 'https://mydhl.express.dhl/us/en/home.html#/schedulePickupTab',
		};

		if ( ! ( pickup_urls.hasOwnProperty( carrierId ) ) ) {
			return null;
		}

		const onClickOpenPage = () => {
			window.open(pickup_urls[ carrierId ], '_blank');
		};

		return (
			<MenuItem onClick={ onClickOpenPage } icon={ <Gridicon icon="external" /> }>
				{ translate( 'Schedule a pickup' ) }
			</MenuItem>
		);
	};

	renderCommercialInvoiceLink () {
		const { translate, label: { commercialInvoiceUrl } } = this.props;

		if ( ! commercialInvoiceUrl ) {
			return null;
		}

		const printCommercialInvoice = () => {
			window.open( commercialInvoiceUrl );
		}

		return (
			<MenuItem onClick={ printCommercialInvoice } icon={ <Gridicon icon="print" /> }>
				{ translate( 'Print customs form' ) }
			</MenuItem>
		);
	}

	render() {
		const {
			label: {
				labelIndex,
				serviceName,
				packageName,
				productNames,
				receiptId,
				labelId,
				createdDate,
				refundableAmount,
				currency,
				showDetails,
				expiryDate,
				anonymized,
				usedDate,
                tracking,
				carrierId,
				commercialInvoiceUrl,
			},
			siteId,
			orderId,
			translate,
			isModal
		} = this.props;

		const todayTime = new Date().getTime();
		let expired = false;
		let refundExpired = false;
		if (
			anonymized ||
			usedDate ||
			( expiryDate && expiryDate < todayTime )
		) {
			expired = true;
		}

		const thirtyDaysAgo = new Date().setDate( (new Date()).getDate() - 30 );
		if (
			anonymized ||
			usedDate ||
			( createdDate && createdDate < thirtyDaysAgo )
		) {
			refundExpired = true;
		}

		return (
			<div className="shipping-label__item">
				<div className="shipping-label__item-detail">
					{ translate( '%(service)s label (#%(labelIndex)d)', {
						args: {
							service: serviceName,
							labelIndex: labelIndex + 1,
						},
					} ) }
					{ showDetails && (
						<span>
							{ ( ! isModal && (
								<DropdownMenu className="shipping-label__item-dropdown-menu" icon={ <Gridicon icon="ellipsis" /> } label={ translate( 'Toggle menu' ) } popoverProps={ { position: 'bottom left' } }>
									{ () => (
										<Fragment>
											<MenuGroup>
												{ this.renderLabelDetails( labelId ) }
												{ this.renderPickup( carrierId ) }
												{ this.renderRefund( labelId, refundExpired, carrierId, tracking ) }
												{ this.renderReprint( labelId, expired ) }
												{ this.renderCommercialInvoiceLink( commercialInvoiceUrl ) }
											</MenuGroup>
										</Fragment>
									) }
								</DropdownMenu>
							) ) }
							<DetailsDialog
								siteId={ siteId }
								orderId={ orderId }
								labelIndex={ labelIndex }
								serviceName={ serviceName }
								packageName={ packageName }
								productNames={ productNames }
								receiptId={ receiptId }
								labelId={ labelId }
							/>
							<RefundDialog
								siteId={ siteId }
								orderId={ orderId }
								createdDate={ createdDate }
								refundableAmount={ refundableAmount }
								currency={ currency }
								labelId={ labelId }
							/>
							<ReprintDialog siteId={ siteId } orderId={ orderId } labelId={ labelId } />
						</span>
					) }
				</div>
				{ showDetails && (
					<p className="shipping-label__item-tracking">
						{ translate( 'Tracking #: {{trackingLink/}}', {
							components: { trackingLink: <TrackingLink carrierId={ carrierId } tracking={ tracking }  /> },
						} ) }
					</p>
				) }
			</div>
		);
	}
}

LabelItem.propTypes = {
	siteId: PropTypes.number.isRequired,
	orderId: PropTypes.number.isRequired,
	label: PropTypes.shape({
		serviceName: PropTypes.string.isRequired,
		labelIndex: PropTypes.number.isRequired,
		packageName: PropTypes.string.isRequired,
		productNames: PropTypes.string.isRequired,
		receiptId: PropTypes.number.isRequired,
		labelId: PropTypes.number.isRequired,
		createdDate: PropTypes.string.isRequired,
		refundableAmount: PropTypes.number.isRequired,
		currency: PropTypes.string.isRequired,
		showDetails: PropTypes.bool.isRequired,
		expiryDate: PropTypes.string.isRequired,
		anonymized: PropTypes.bool.isRequired,
		usedDate: PropTypes.string.isRequired,
		tracking: PropTypes.string.isRequired,
		carrierId: PropTypes.string.isRequired,
		commercialInvoiceUrl: PropTypes.string,
	}).isRequired,
	isModal: PropTypes.bool.isRequired,
	openRefundDialog: PropTypes.func.isRequired,
	openReprintDialog: PropTypes.func.isRequired,
	openDetailsDialog: PropTypes.func.isRequired,
};

const mapDispatchToProps = dispatch => {
	return bindActionCreators( { openRefundDialog, openReprintDialog, openDetailsDialog }, dispatch );
};

export default connect(
	null,
	mapDispatchToProps
)( localize( LabelItem ) );
