import { components, html, i18n } from '../utils/index.js'
import CompositionSolutions from './composition-solutions.js'
import CompositionParts from './composition-parts.js'
import CompositionComposerJson from './composition-composerjson.js'

const {Placeholder} = components
const {__} = i18n

function CompositionPlaceholder (props) {
	return html`
	  <${Placeholder}
			  label=${__('No composition details', 'pixelgradelt_retailer')}
			  instructions=${__('Probably you need to do some configuring first. Go on.. don\'t be shy..', 'pixelgradelt_retailer')}
	  >
	  </${Placeholder}>
	`
}

function CompositionState (props) {
	// If we have no solutions, all is lost :(
	if (!props.solutions.length) {
		return html`
		<${CompositionPlaceholder}/>
		`
	}

	return html`
	  <${CompositionSolutions} solutions=${props.solutions}/>
	  <${CompositionParts} parts=${props.parts}/>
	  <${CompositionComposerJson} composerJson=${props.composerJson}/>
	`
}

export default CompositionState
