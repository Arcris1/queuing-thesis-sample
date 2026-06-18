import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:smart_queue_mobile/data/api_client.dart';
import 'package:smart_queue_mobile/features/auth/auth_controller.dart';
import 'package:smart_queue_mobile/features/auth/auth_repository.dart';
import 'package:smart_queue_mobile/features/auth/auth_user.dart';
import 'package:smart_queue_mobile/features/auth/login_screen.dart';
import 'package:smart_queue_mobile/features/home/home_screen.dart';
import 'package:smart_queue_mobile/theme/app_theme.dart';

/// In-memory fake of [AuthRepository] so tests never touch Dio or secure
/// storage. Lets each test script success/failure deterministically.
class FakeAuthRepository implements AuthRepository {
  FakeAuthRepository({
    this.storedToken,
    this.loginResult,
    this.registerResult,
    this.loginError,
    this.registerError,
    this.meUser,
  });

  String? storedToken;
  AuthUser? loginResult;
  AuthUser? registerResult;
  Object? loginError;
  Object? registerError;
  AuthUser? meUser;

  @override
  Future<String?> readToken() async => storedToken;

  @override
  Future<AuthUser> me() async {
    final u = meUser;
    if (u == null) throw const ApiException('Unauthorized', statusCode: 401);
    return u;
  }

  @override
  Future<AuthUser> login({
    required String email,
    required String password,
  }) async {
    if (loginError != null) throw loginError!;
    return loginResult!;
  }

  @override
  Future<AuthUser> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? studentNo,
  }) async {
    if (registerError != null) throw registerError!;
    return registerResult!;
  }

  @override
  Future<void> logout() async {
    storedToken = null;
  }
}

Widget _wrap(FakeAuthRepository repo, {Widget? home}) {
  return ProviderScope(
    overrides: [authRepositoryProvider.overrideWithValue(repo)],
    child: MaterialApp(
      theme: AppTheme.light(),
      home: home ?? const LoginScreen(),
    ),
  );
}

const _testUser = AuthUser(
  id: 1,
  name: 'Ada Lovelace',
  email: 'ada@school.edu',
  role: 'student',
  studentNo: '2025-0001',
);

void main() {
  testWidgets('Login screen renders the branded sign-in form',
      (tester) async {
    await tester.pumpWidget(_wrap(FakeAuthRepository()));
    await tester.pump();

    expect(find.text('Welcome back'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Sign in'), findsOneWidget);
    expect(find.widgetWithText(TextButton, 'Create one'), findsOneWidget);
  });

  testWidgets('Empty submit surfaces inline validation errors',
      (tester) async {
    await tester.pumpWidget(_wrap(FakeAuthRepository()));
    await tester.pump();

    await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
    await tester.pumpAndSettle();

    expect(find.text('Email is required'), findsOneWidget);
    expect(find.text('Password is required'), findsOneWidget);
  });

  testWidgets('Invalid email is rejected by the validator', (tester) async {
    await tester.pumpWidget(_wrap(FakeAuthRepository()));
    await tester.pump();

    await tester.enterText(
        find.widgetWithText(TextFormField, 'Email'), 'not-an-email');
    await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
    await tester.pumpAndSettle();

    expect(find.text('Enter a valid email address'), findsOneWidget);
  });

  testWidgets('Bad credentials surface a 401 error message', (tester) async {
    final repo = FakeAuthRepository(
      loginError: const ApiException('Incorrect email or password.',
          statusCode: 401),
    );
    await tester.pumpWidget(_wrap(repo));
    await tester.pump();

    await tester.enterText(
        find.widgetWithText(TextFormField, 'Email'), 'ada@school.edu');
    await tester.enterText(
        find.widgetWithText(TextFormField, 'Password'), 'wrongpass');
    await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
    await tester.pumpAndSettle();

    expect(find.text('Incorrect email or password.'), findsOneWidget);
  });

  testWidgets('Successful login authenticates the controller',
      (tester) async {
    final repo = FakeAuthRepository(loginResult: _testUser);
    late WidgetRef capturedRef;

    await tester.pumpWidget(
      ProviderScope(
        overrides: [authRepositoryProvider.overrideWithValue(repo)],
        child: MaterialApp(
          theme: AppTheme.light(),
          home: Consumer(builder: (context, ref, _) {
            capturedRef = ref;
            return const LoginScreen();
          }),
        ),
      ),
    );
    await tester.pump();

    await tester.enterText(
        find.widgetWithText(TextFormField, 'Email'), 'ada@school.edu');
    await tester.enterText(
        find.widgetWithText(TextFormField, 'Password'), 'password123');
    await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
    await tester.pumpAndSettle();

    final state = capturedRef.read(authControllerProvider);
    expect(state.user, _testUser);
  });

  testWidgets('Logout from home clears the session', (tester) async {
    final repo = FakeAuthRepository(storedToken: 'tok', meUser: _testUser);
    late WidgetRef capturedRef;

    await tester.pumpWidget(
      ProviderScope(
        overrides: [authRepositoryProvider.overrideWithValue(repo)],
        child: MaterialApp(
          theme: AppTheme.light(),
          home: Consumer(builder: (context, ref, _) {
            capturedRef = ref;
            return const HomeScreen();
          }),
        ),
      ),
    );
    await tester.pump();

    // Restore the session (stored token → /api/me) so the user is present.
    await capturedRef.read(authControllerProvider.notifier).bootstrap();
    await tester.pumpAndSettle();

    expect(find.text('Ada Lovelace'), findsOneWidget);

    await tester.tap(find.widgetWithText(TextButton, 'Log out'));
    await tester.pumpAndSettle();

    expect(capturedRef.read(authControllerProvider).user, isNull);
  });
}
