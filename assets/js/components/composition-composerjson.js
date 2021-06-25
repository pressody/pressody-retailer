import { components, html, i18n } from '../utils/index.js'

const {Placeholder} = components
const {__} = i18n

function CompositionComposerJsonPlaceholder (props) {
	return html`
	  <${Placeholder}
			  label=${__('No composer.json', 'pixelgradelt_retailer')}
			  instructions=${__('Add some solutions to this composition if you want it to do something. Also, make sure to have some valid composition user details, since these will be validated on composer.json generation.', 'pixelgradelt_retailer')}
	  >
	  </${Placeholder}>
	`
}

const isEmpty = (obj) => {
	if (typeof obj === 'object' && obj != null) {
		return Object.keys(obj).length < 1;
	}
	return true;
};

function CompositionComposerJson (props) {
	if (isEmpty(props.composerJson)) {
		return html`
		<${CompositionComposerJsonPlaceholder}/>
		`
	}

	const {
		composerJson,
	} = props

	return html`
			<pre className="pixelgradelt_retailer-composer-snippet"><code>${ JSON.stringify( composerJson, null, 2 ) }</code></pre>
	`
}

export default CompositionComposerJson
