import { components, html, i18n } from '../utils/index.js'
import SolutionTable from './solution-table.js'

const {Placeholder} = components
const {__} = i18n

function SolutionPlaceholder (props) {
	return html`
	  <${Placeholder}
			  label=${__('No solution details', 'pixelgradelt_retailer')}
			  instructions=${__('Probably you need to do some configuring first. Go on.. don\'t be shy..', 'pixelgradelt_retailer')}
	  >
	  </${Placeholder}>
	`
}

function SolutionPreview (props) {
	if (!props.solutions.length) {
		return html`
		<${SolutionPlaceholder}/>
		`
	}

	return props.solutions.map((item, index) =>
		html`
		<${SolutionTable} key=${item.name} ...${item}/>`
	)
}

export default SolutionPreview
