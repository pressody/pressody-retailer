import { components, html, i18n } from '../utils/index.js'
import PackageAuthors from './package-authors.js'
import SolutionRequiredPackages from './solution-required-packages.js'

const {Placeholder} = components
const {__} = i18n

function CompositionSolutionsPlaceholder (props) {
	return html`
	  <${Placeholder}
			  label=${__('No solutions', 'pressody_retailer')}
			  instructions=${__('Add some solutions to this composition if you want it to do something.', 'pressody_retailer')}
	  >
	  </${Placeholder}>
	`
}

function CompositionSolutions (props) {
	if (!props.solutions.length) {
		return html`
		<${CompositionSolutionsPlaceholder}/>
		`
	}

	return props.solutions.map((item, index) =>
		html`
		<${CompositionSolution} key=${item.name} ...${item}/>
		`
	)
}

function CompositionSolution (props) {
	const {
		authors,
		composer,
		description,
		name,
		homepage,
		categories,
		keywords,
		releases,
		requiredPackages,
		excludedPackages,
		slug,
		type,
		visibility,
		editLink,
	} = props

	return html`
	  <table className="pressody_retailer-package widefat">
		  <thead>
		  <tr>
					<th colSpan="2"><strong>${composer.name}</strong>
				  ${'public' !== visibility ? '(' + visibility[0].toUpperCase() + visibility.slice(1) + ')' : ''}
							<a className="edit-package" href=${editLink}>Edit solution</a>
					</th>
		  </tr>
		  </thead>
		  <tbody>
		  <tr>
			  <th>${__('Required Packages', 'pressody_retailer')}</th>
			  <td className="pressody_retailer-required-packages">
				  <${SolutionRequiredPackages} requiredPackages=${requiredPackages}/>
			  </td>
		  </tr>
		  </tbody>
	  </table>
	`
}

export default CompositionSolutions
