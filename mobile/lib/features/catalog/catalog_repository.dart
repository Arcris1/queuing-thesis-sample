import '../../data/api_client.dart';
import 'office.dart';
import 'office_services.dart';

/// Talks to the public catalog endpoints (§7).
///
/// `GET /api/offices` and `GET /api/offices/{office}/services` are public, so
/// no token is required — the [ApiClient] interceptor simply omits the bearer
/// header when none is stored.
class CatalogRepository {
  CatalogRepository({required ApiClient apiClient}) : _api = apiClient;

  final ApiClient _api;

  /// GET /api/offices → list of offices.
  ///
  /// The list endpoint returns `{ data: [...] }`; [ApiClient.get] unwraps to a
  /// map, so we read the list back via a dedicated raw call.
  Future<List<Office>> getOffices() async {
    final list = await _api.getList('/offices');
    return list
        .whereType<Map<String, dynamic>>()
        .map(Office.fromJson)
        .toList(growable: false);
  }

  /// GET /api/offices/{office}/services → office + services grouped by group.
  Future<OfficeServices> getServices(int officeId) async {
    final data = await _api.get('/offices/$officeId/services');
    return OfficeServices.fromJson(data);
  }
}
