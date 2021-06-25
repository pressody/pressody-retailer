import { components, html, i18n } from '../utils/index.js'
import SolutionRequiredPackages from './solution-required-packages.js'

const {Button, Placeholder} = components
const {__} = i18n

function CompositionPartsPlaceholder (props) {
	return html`
	  <${Placeholder}
			  label=${__('No parts', 'pixelgradelt_retailer')}
			  instructions=${__('You need to do some more configuring.', 'pixelgradelt_retailer')}
	  >
	  </${Placeholder}>
	`
}

function CompositionParts (props) {
	if (!props.parts.length) {
		return html`
		<${CompositionPartsPlaceholder}/>
		`
	}

	return props.parts.map((item, index) =>
		html`
		<${CompositionPart} key=${item.name} ...${item}/>
		`
	)
}

function CompositionPart (props) {
	const {
		name,
		version,
		requiredBy,
	} = props

	const requiredByList = requiredBy.map( ( solution, index ) => {

		let className = 'button pixelgradelt_retailer-required-by-solution';

		return html`
			<${ Button }
				key=${ name+solution.name }
				className=${ className }
				href=${ '#' }
				target="_blank"
				rel="noopener noreferer"
			>
				${ solution.name } (ver. req. ${ solution.requiredVersion })
			</${ Button }>
			${ ' ' }
		`;
	} );

	return html`
	  <table className="pixelgradelt_retailer-package widefat">
		  <thead>
		  <tr>
			  <th colSpan="2"><strong>${name} : ${version}</strong></th>
		  </tr>
		  </thead>
		  <tbody>
		  <tr>
			  <th>${__('Required By Solutions', 'pixelgradelt_retailer')}</th>
			  <td className="pixelgradelt_retailer-required-by-solutions">
				  ${requiredByList}
			  </td>
		  </tr>
		  </tbody>
	  </table>
	`
}

export default CompositionParts
