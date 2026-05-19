<?php
session_start();
require_once 'includes/db.php';

// Sécurité: uniquement les admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /');
    exit;
}

$message = "";

// Action de l'admin
// creation d'un comte employé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_creer_employe'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $mot_de_passe = $_POST['mot_de_passe'];

    // Vérification du mot de passe
    $regex_mdp = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{10,}$/';

    if (!preg_match($regex_mdp, $mot_de_passe)) {
        $message = "<div class='alert-error'>Le mot de passe doit contenir 10 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.</div>";
    } else {
        // Vérifier si l'email existe déjà
        $check_email = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
        $check_email->execute([$email]);

        if ($check_email->rowCount() > 0) {
            $message = "<div class='alert-error'>Cet email est déjà utilisé.</div>";
        } else {
            // Hachage et Insertion role employé
            $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role, statut_compte) VALUES (?, ?, ?, ?, 'employe', 'actif')");

            if ($insert->execute([$nom, $prenom, $email, $hash])) {
                // Simulation de l'envoi d'email
                $message = "<div class='alert-success'>Compte employé créé avec succès ! Un mail de notification a été envoyé à $email.</div>";
            }
        }
    }
}

// activer ou désactiver un compte employé
if (isset($_GET['action_statut']) && isset($_GET['id_employe'])) {
    $id_employe = (int)$_GET['id_employe'];
    $nouveau_statut = $_GET['action_statut'] === 'desactiver' ? 'inactif' : 'actif';

    $update = $pdo->prepare("UPDATE utilisateur SET statut_compte = ? WHERE id_utilisateur = ? AND role = 'employe'");
    if ($update->execute([$nouveau_statut, $id_employe])) {
        $message = "<div class='alert-success'>Le statut de l'employé a été mis à jour.</div>";
    }
}

// récuoération des données
// Récupérer les employés pour la gestion
$req_employes = $pdo->query("SELECT * FROM utilisateur WHERE role = 'employe' ORDER BY nom ASC");
if ($req_employes) {
    $employes = $req_employes->fetchAll(PDO::FETCH_ASSOC);
} else {
    $employes = [];
}

include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <div class="d-flex justify-content-between align-items-center mb-5 border-bottom border-warning pb-3">
        <h2 class="logo-font text-gold m-0">Espace Administrateur</h2>
        <a href="espace_employe" class="btn-outline border-warning text-warning" style="padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none;">
            <i class="fa-solid fa-arrow-right"></i> Aller au panel Employé
        </a>
    </div>

    <?php if(!empty($message)) echo $message; ?>

    <div class="dashboard-grid">

        <div class="glass-panel p-4">
            <h4 class="text-white mb-4 border-bottom border-secondary pb-2"><i class="fa-solid fa-user-plus"></i> Nouvel Employé</h4>
            <form method="POST" action="">
                <input type="hidden" name="action_creer_employe" value="1">

                <div class="mb-3">
                    <label class="form-label">Nom</label>
                    <input type="text" name="nom" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Prénom</label>
                    <input type="text" name="prenom" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email professionnel</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mot de passe provisoire</label>
                    <input type="text" name="mot_de_passe" class="form-control" required>
                    <small class="text-muted">10 car. min, 1 maj, 1 min, 1 chiffre, 1 car. spécial.</small>
                </div>

                <button type="submit" class="btn-primary w-100 border-0 mt-3">Créer le compte</button>
            </form>
        </div>

        <div class="glass-panel p-4">
            <h4 class="text-white mb-4 border-bottom border-secondary pb-2"><i class="fa-solid fa-users-gear"></i> Équipe Actuelle</h4>

            <?php if(empty($employes)): ?>
                <p class="text-muted fst-italic">Aucun employé créé pour le moment</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Nom / Prénom</th>
                                <th>Email</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($employes as $emp): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($emp['nom'] . ' ' . $emp['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                    <td>
                                        <?php if($emp['statut_compte'] === 'actif'): ?>
                                            <span class="badge-status badge-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-waiting border-danger text-danger bg-transparent">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($emp['statut_compte'] === 'actif'): ?>
                                            <a href="?action_statut=desactiver&id_employe=<?php echo $emp['id_utilisateur']; ?>"
                                               class="btn-action-small btn-outline text-danger border-danger"
                                               onclick="return confirm('Rendre ce compte inutilisable ?');">Désactiver</a>
                                        <?php else: ?>
                                            <a href="?action_statut=activer&id_employe=<?php echo $emp['id_utilisateur']; ?>"
                                               class="btn-action-small btn-outline text-success border-success">Réactiver</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="mt-5 pt-4 border-top border-secondary">
              <h4 class="text-gold mb-4"><i class="fa-solid fa-chart-pie"></i> Statistiques des Commandes (MongoDB)</h4>

              <?php
              $noms_menus = [];
              $nb_commandes = [];
              $erreur_mongo = "";

              try {
                  // connexion native à MongoDB (Le pont entre PHP et NoSQL)
                  $mongo = new MongoDB\Driver\Manager("mongodb://localhost:27017");

                  // preparation la requête pour tout lire
                  $query = new MongoDB\Driver\Query([]);

                  // execution la requête sur notre base et collection créées dans Compass
                  $cursor = $mongo->executeQuery('vite_et_gourmand_nosql.statistiques_menus', $query);

                  // rangement des données dans nos tableaux pour le graphique
                  foreach ($cursor as $document) {
                      $noms_menus[] = $document->nom_menu;
                      $nb_commandes[] = $document->nombre_commandes;
                  }
              } catch (Exception $e) {
                  $erreur_mongo = "Erreur de connexion à MongoDB. Vérifiez que votre serveur MongoDB est bien allumé.";
              }
              ?>

              <?php if (!empty($erreur_mongo)): ?>
                  <div class="alert-waiting text-center p-3 border border-danger text-danger bg-transparent rounded"><?php echo $erreur_mongo; ?></div>
              <?php elseif (empty($noms_menus)): ?>
                  <div class="alert-waiting text-center p-3 border border-warning text-warning bg-transparent rounded">Aucune donnée trouvée dans la base NoSQL.</div>
              <?php else: ?>

                  <div class="p-3 bg-white rounded shadow-sm">
                      <canvas id="graphiqueCommandes" height="100"></canvas>
                  </div>

                  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                  <script>
                      // On convertit les tableaux PHP en tableaux JavaScript compréhensibles par Chart.js
                      const labelsMenus = <?php echo json_encode($noms_menus); ?>;
                      const dataCommandes = <?php echo json_encode($nb_commandes); ?>;

                      const ctx = document.getElementById('graphiqueCommandes').getContext('2d');
                      new Chart(ctx, {
                          type: 'bar', // Un graphique en barres parfait pour comparer des quantités
                          data: {
                              labels: labelsMenus,
                              datasets: [{
                                  label: 'Nombre de commandes par Menu',
                                  data: dataCommandes,
                                  backgroundColor: [
                                      'rgba(245, 158, 11, 0.8)', // Doré Vite & Gourmand
                                      'rgba(59, 130, 246, 0.8)', // Bleu
                                      'rgba(16, 185, 129, 0.8)', // Vert
                                      'rgba(239, 68, 68, 0.8)'   // Rouge
                                  ],
                                  borderColor: [
                                      'rgb(245, 158, 11)',
                                      'rgb(59, 130, 246)',
                                      'rgb(16, 185, 129)',
                                      'rgb(239, 68, 68)'
                                  ],
                                  borderWidth: 1,
                                  borderRadius: 4
                              }]
                          },
                          options: {
                              responsive: true,
                              plugins: {
                                  legend: {
                                      display: true,
                                      position: 'top',
                                  }
                              },
                              scales: {
                                  y: {
                                      beginAtZero: true,
                                      title: {
                                          display: true,
                                          text: 'Quantité vendue'
                                      }
                                  }
                              }
                          }
                      });
                  </script>
              <?php endif; ?>
          </div>

        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
