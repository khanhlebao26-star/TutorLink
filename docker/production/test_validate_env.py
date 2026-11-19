import unittest
from validate_env import validate
class DeploymentEnvironmentTest(unittest.TestCase):
    def fixture(self):
        return dict(APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',DB_PASSWORD='secret',REVERB_APP_KEY='public-key',REVERB_APP_SECRET='private-secret',MAIL_HOST='smtp.test.invalid',MAIL_USERNAME='mailer',MAIL_PASSWORD='secret',RELEASE_TAG='abc123',APP_ENV='production',APP_DEBUG='false',SESSION_DRIVER='database',QUEUE_CONNECTION='database',SESSION_SECURE_COOKIE='true',SESSION_SAME_SITE='lax',APP_URL='https://api.tutorlink.test',FRONTEND_URL='https://app.tutorlink.test',SESSION_DOMAIN='.tutorlink.test',APP_DOMAIN='api.tutorlink.test',SANCTUM_STATEFUL_DOMAINS='app.tutorlink.test',REVERB_ALLOWED_ORIGINS='app.tutorlink.test')
    def test_valid_custom_domains(self): self.assertEqual(validate(self.fixture()),[])
    def test_rejects_cookie_mismatch_and_debug(self):
        values=self.fixture();values.update(SESSION_DOMAIN='.wrong.test',APP_DEBUG='true')
        self.assertIn('SESSION_DOMAIN must cover the API and frontend custom domains',validate(values))
        self.assertIn('APP_DEBUG must be false',validate(values))
    def test_rejects_missing_secrets(self):
        values=self.fixture();values['DB_PASSWORD']=''
        self.assertIn('DB_PASSWORD must be configured',validate(values))
if __name__=='__main__': unittest.main()
