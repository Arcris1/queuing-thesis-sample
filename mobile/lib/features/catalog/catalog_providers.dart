import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/auth_controller.dart';
import 'catalog_repository.dart';
import 'office_services.dart';
import 'office.dart';

/// Public catalog endpoints (offices + grouped services). Overridable in tests.
final catalogRepositoryProvider = Provider<CatalogRepository>((ref) {
  return CatalogRepository(apiClient: ref.watch(apiClientProvider));
});

/// The list of offices for the office-select screen. Auto-disposed, refreshable
/// via `ref.invalidate(officesProvider)` on retry.
final officesProvider = FutureProvider.autoDispose<List<Office>>((ref) {
  return ref.watch(catalogRepositoryProvider).getOffices();
});

/// Services for a single office, grouped by queue group. Keyed by office id so
/// each office's services load independently.
final officeServicesProvider =
    FutureProvider.autoDispose.family<OfficeServices, int>((ref, officeId) {
  return ref.watch(catalogRepositoryProvider).getServices(officeId);
});
