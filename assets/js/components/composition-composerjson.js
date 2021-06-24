import { components, html, i18n } from '../utils/index.js'
import PackageAuthors from './package-authors.js'
import SolutionRequiredPackages from './solution-required-packages.js'

const {Placeholder} = components
const {__} = i18n

function CompositionComposerJsonPlaceholder (props) {
	return html`
	  <${Placeholder}
			  label=${__('No composer.json', 'pixelgradelt_retailer')}
			  instructions=${__('Add some solutions to this composition if you want it to do something. Also, make sure to have some valid composition user details, since these will be validated on composer.json creation.', 'pixelgradelt_retailer')}
	  >
	  </${Placeholder}>
	`
}

function CompositionComposerJson (props) {
	if (!props.composerJson.length) {
		return html`
		<${CompositionComposerJsonPlaceholder}/>
		`
	}

	const {
		composerJson,
	} = props

	return html`
	  <pre>${ composerJson }</pre>
	`
}

export default CompositionComposerJson
