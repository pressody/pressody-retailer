import { components, html, i18n } from '../utils/index.js';
import ApiKeyForm from './api-key-form.js';
import { moreVertical } from './icons.js';

const { DropdownMenu, TextControl } = components;
const { __ } = i18n;

const selectField = ( e ) => e.nativeEvent.target.select();

function AccessTable( props ) {
	const {
		apiKeys,
		onCreateApiKey,
		onRevokeApiKey,
	} = props;

	let body = html`<tr><td colSpan=6>${ __( 'Add an API Key to access the PixelgradeLT Retailer repository.', 'pixelgradelt_retailer' ) }</td></tr>`;

	if ( apiKeys.length ) {
		body = apiKeys.map( ( item, index ) => {
			return html`
				<${ AccessTableRow }
					key=${ item.token }
					onRevokeApiKey=${ onRevokeApiKey }
					...${ item }
				/>
			`;
		} );
	}

	return html`
		<table className="pixelgradelt_retailer-api-key-table widefat">
			<thead>
				<tr>
					<th>${ __( 'Name', 'pixelgradelt_retailer' ) }</th>
					<th className="column-user">${ __( 'User', 'pixelgradelt_retailer' ) }</th>
					<th>${ __( 'API Key', 'pixelgradelt_retailer' ) }</th>
					<th>${ __( 'Last Used', 'pixelgradelt_retailer' ) }</th>
					<th>${ __( 'Created', 'pixelgradelt_retailer' ) }</th>
					<th></th>

				</tr>
			</thead>
			<tbody>${ body }</tbody>
			<tfoot>
				<tr>
					<td colSpan="6" className="pixelgradelt_retailer-api-key-form">
						<${ ApiKeyForm }
							onSubmit=${ onCreateApiKey }
						/>
					</td>
				</tr>
			</tfoot>
		</table>
	`;
};

function AccessTableRow( props ) {
	const {
		created,
		last_used,
		name,
		token,
		user,
		user_login,
		onRevokeApiKey,
	} = props;

	return html `
		<tr key="${ token }">
			<th scope="row">${ name }</th>
			<td className="column-user">${ user_login }</td>
			<td className="column-token">
				<${ TextControl }
					className="regular-text"
					value=${ token }
					readOnly
					onClick=${ selectField }
				/>
			</td>
			<td className="column-last-used">${ last_used || '—' }</td>
			<td className="column-created">${ created }</td>
			<td className="column-actions">
				<${ DropdownMenu }
					label=${ __( 'Toggle dropdown', 'pixelgradelt_retailer' ) }
					icon=${ moreVertical }
					controls=${ [
						{
							title: __( 'Revoke', 'pixelgradelt_retailer' ),
							onClick: () => { onRevokeApiKey( token, user ) }
						}
					] }
				/>
			</td>
		</tr>
	`;
};

export default AccessTable;
