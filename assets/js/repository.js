import { data, element, html } from './utils/index.js';
import Repository from './components/repository.js';
import './data/solutions.js';

const { useSelect } = data;
const { Fragment, render } = element;

// noinspection JSUnresolvedVariable,JSHint
const { addNewSolutionUrl } = _pixelgradeltRetailerRepositoryData;

function App() {
	const solutions = useSelect( select => select( 'pixelgradelt_retailer/solutions' ).getSolutions() );

	return html`
		<${ Fragment }>
			<${ Repository }
				solutions=${ solutions }
				addNewSolutionUrl=${ addNewSolutionUrl }
			/>
		</${ Fragment }>
	`;
}

render(
	html`<${ App } />`,
	document.getElementById( 'pixelgradelt_retailer-repository-container' )
);
