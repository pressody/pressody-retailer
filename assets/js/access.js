import { data, element, html } from './utils/index.js';
import AccessTable from './components/access-table.js';
import './data/access.js';

const { useDispatch, useSelect } = data;
const { render } = element;

// noinspection JSUnresolvedVariable,JSHint
const { editedUserId } = _pixelgradeltRetailerAccessData;

function App( props ) {
	const { userId } = props;

	const {
		createApiKey,
		setUserId,
		revokeApiKey,
	} = useDispatch( 'pixelgradelt_retailer/access' );

	setUserId( userId );

	const apiKeys = useSelect( ( select ) => {
		return select( 'pixelgradelt_retailer/access' ).getApiKeys();
	} );

	return html`
		<${ AccessTable }
			apiKeys=${ apiKeys }
			userId=${ userId }
			onCreateApiKey=${ ( name ) => createApiKey( name, userId ) }
			onRevokeApiKey=${ revokeApiKey }
		/>
	`;
}

render(
	html`<${ App } userId=${ editedUserId } />`,
	document.getElementById( 'pixelgradelt_retailer-api-key-manager' )
);
