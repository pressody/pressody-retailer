import { components, html, i18n } from '../utils/index.js';
import SolutionTable from './solution-table.js';

const { Button, Placeholder } = components;
const { __ } = i18n;

function RepositoryPlaceholder( props ) {
	return html`
		<${ Placeholder }
			label=${ __( 'Add Solutions', 'pixelgradelt_retailer' ) }
			instructions=${ __( 'You have not configured any Pixelgrade LT Solutions.', 'pixelgradelt_retailer' ) }
			className="pixelgradelt_retailer-repository-placeholder"
		>
			<${ Button }
				isPrimary
				href= ${ props.addNewSolutionUrl }
			>
				${ __( 'Add Solution', 'pixelgradelt_retailer' ) }
			</${ Button }>
		</${ Placeholder }>
	`;
}

function Repository( props ) {
	if ( ! props.solutions.length ) {
		return html`
			<${ RepositoryPlaceholder } addNewSolutionUrl=${ props.addNewSolutionUrl } />
		`;
	}

	return props.solutions.map( ( item, index ) =>
		html`<${ SolutionTable } key=${ item.slug } ...${ item } />`
	);
}

export default Repository;
