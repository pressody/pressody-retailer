import { html, i18n } from '../utils/index.js';
import Releases from './releases.js';
import PackageAuthors from './package-authors.js';
import SolutionRequiredPackages from './solution-required-packages.js';
import PackageKeywords from './package-keywords.js';

const { __ } = i18n;

function SolutionTable( props ) {
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
	} = props;

	return html`
		<table className="pixelgradelt_retailer-package widefat">
			<thead>
				<tr>
					<th colSpan="2">${ composer.name } ${ 'public' !== visibility ? '(' + visibility[0].toUpperCase() + visibility.slice(1) + ')' : '' } <a className="edit-package" href=${ editLink }>Edit solution</a></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td colSpan="2">${ description }</td>
				</tr>
				<tr>
					<th>${ __( 'Homepage', 'pixelgradelt_retailer' ) }</th>
					<td><a href="${ homepage }" target="_blank" rel="noopener noreferer">${ homepage }</a></td>
				</tr>
				<tr>
					<th>${ __( 'Authors', 'pixelgradelt_retailer' ) }</th>
					<td className="package-authors__list">
						<${ PackageAuthors } authors=${ authors } />
					</td>
				</tr>
				<tr>
					<th>${ __( 'Releases', 'pixelgradelt_retailer' ) }</th>
					<td className="pixelgradelt_retailer-releases">
						<${ Releases } releases=${ releases } name=${ name } composer=${ composer } />
					</td>
				</tr>
				<tr>
					<th>${ __( 'Required Packages', 'pixelgradelt_retailer' ) }</th>
					<td className="pixelgradelt_retailer-required-packages">
						<${ SolutionRequiredPackages } requiredPackages=${ requiredPackages } />
					</td>
				</tr>
				<tr>
					<th>${ __( 'Excluded Solutions', 'pixelgradelt_retailer' ) }</th>
					<td className="pixelgradelt_retailer-required-packages pixelgradelt_retailer-excluded-solutions">
						<${ SolutionRequiredPackages } requiredPackages=${ excludedPackages } />
					</td>
				</tr>
				<tr>
					<th>${ __( 'Categories', 'pixelgradelt_retailer' ) }</th>
					<td className="package-keywords__list package-categories__list">
						<${ PackageKeywords } keywords=${ categories } />
					</td>
				</tr>
				<tr>
					<th>${ __( 'Keywords', 'pixelgradelt_retailer' ) }</th>
					<td className="package-keywords__list">
						<${ PackageKeywords } keywords=${ keywords } />
					</td>
				</tr>
				<tr>
					<th>${ __( 'Package Type', 'pixelgradelt_retailer' ) }</th>
					<td><code>${ composer.type }</code></td>
				</tr>
			</tbody>
		</table>
	`;
};

export default SolutionTable;
