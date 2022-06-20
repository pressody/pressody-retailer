import { data, element, html } from './utils/index.js';
import AccessTable from './components/access-table.js';
import './data/access.js';

const { useDispatch, useSelect } = data;
const { render } = element;

// noinspection JSUnresolvedVariable,JSHint
const { editedUserId } = _pressodyRetailerAccessData;

function App( props ) {
	const { userId } = props;

	const {
		createApiKey,
		setUserId,
		revokeApiKey,
	} = useDispatch( 'pressody_retailer/access' );

	setUserId( userId );

	const apiKeys = useSelect( ( select ) => {
		return select( 'pressody_retailer/access' ).getApiKeys();
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
	document.getElementById( 'pressody_retailer-api-key-manager' )
);
