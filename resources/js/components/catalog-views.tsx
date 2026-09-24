import { Tabs } from '@/components/admin-controls';
import { demand, index, refusals, removed } from '@/routes/admin/catalog';

/**
 * The views under Admin → Catalog (spec 16.5, 16.1.5, 16.1.6).
 */

const VIEWS = [
    { id: 'modules', title: 'Modules', href: index() },
    { id: 'removed', title: 'Removed', href: removed() },
    { id: 'demand', title: 'Demand', href: demand() },
    { id: 'refusals', title: 'Refusals', href: refusals() },
] as const;

export default function CatalogViews({
    current,
}: {
    current: (typeof VIEWS)[number]['id'];
}) {
    return <Tabs views={VIEWS} current={current} />;
}
