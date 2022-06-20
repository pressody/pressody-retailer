import { components, data, html, i18n } from '../utils/index.js'

const {Button, Placeholder} = components
const {useSelect} = data
const {__} = i18n

function CompositionPartsPlaceholder (props) {
	return html`
	  <${Placeholder}
			  label=${__('No parts', 'pressody_retailer')}
			  instructions=${__('You need to do some more configuring.', 'pressody_retailer')}
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

	const solutionsList = useSelect((select) => {
		return select('pressody_retailer/composition').getSolutions()
	})

	const requiredByList = requiredBy.map( ( solution, index ) => {

		let className = 'button pressody_retailer-required-by-solution';

		// Find the solution that requires this PD part.
		let solutionEditLink = '#';
		if ( solutionsList.length ) {
			const solutionDetails = solutionsList.find((item) => {
				return item.composer.name === solution.name;
			})

			if ( undefined !== solutionDetails ) {
				solutionEditLink = solutionDetails.editLink;
			}
		}

		return html`
			<${ Button }
				key=${ name+solution.name }
				className=${ className }
				href=${ solutionEditLink }
				target="_blank"
				rel="noopener noreferer"
			>
				${ solution.name } (ver. req. ${ solution.requiredVersion })
			</${ Button }>
			${ ' ' }
		`;
	} );

	return html`
	  <table className="pressody_retailer-package widefat">
		  <thead>
		  <tr>
			  <th colSpan="2"><strong>${name} : ${version}</strong></th>
		  </tr>
		  </thead>
		  <tbody>
		  <tr>
			  <th>${__('Required By Solutions', 'pressody_retailer')}</th>
			  <td className="pressody_retailer-required-by-solutions">
				  ${requiredByList}
			  </td>
		  </tr>
		  </tbody>
	  </table>
	`
}

export default CompositionParts
