<?php
	class M_User extends CI_Model
	{
		public function __construct()
        {
            parent::__construct();
            $this->db = $this->load->database('main', true);
        }

        public function insert($tablename, $data){
            $this->db->insert($tablename, $data);
        }

        public function getAllSkpd(){
            return $this->db->select('id_unitkerja, nm_unitkerja')
                            ->from('db_pegawai.unitkerja')
                            ->order_by('nm_unitkerja', 'asc')
                            ->get()->result_array();
        }

        public function getAllUsers(){
            return $this->db->select('a.*, a.nama as nama_user, b.nama_sub_bidang')
                            ->from('m_user a')
                            ->join('m_sub_bidang b', 'a.id_m_sub_bidang = b.id', 'left')
                            ->where('a.flag_active', 1)
                            ->order_by('a.nama')
                            ->get()->result_array();
        }

        public function getAllUsersBySkpd($id_unitkerja){
            return $this->db->select('a.*, a.nama as nama_user, b.nama_sub_bidang, d.nama_bidang')
                            ->from('m_user a')
                            ->join('m_sub_bidang b', 'a.id_m_sub_bidang = b.id', 'left')
                            ->join('db_pegawai.pegawai c', 'a.username = c.nipbaru_ws')
                            ->join('m_bidang d', 'b.id_m_bidang = d.id', 'left')
                            ->where('c.skpd', $id_unitkerja)
                            ->where('a.flag_active', 1)
                            ->order_by('a.nama')
                            ->get()->result_array();
        }

        public function createUser($data){
            if($data['password'] != $data['konfirmasi_password']){
                return ['message' => 'Password dan Konfirmasi Password harus sama'];
            }
            if(strlen($data['password']) < 6){
                return ['message' => 'Panjang Password harus lebih dari 6 karakter'];
            }
            $exist = $this->db->select('username')
                                ->from('m_user')
                                ->where('username', $data['username'])
                                ->where('flag_active', 1)
                                ->get()->row_array();
            if($exist){
                return ['message' => 'Username sudah digunakan'];
            }
            unset($data['konfirmasi_password']);
            $data['password'] = $this->general_library->encrypt($data['username'], $data['password']);
            $this->db->insert('m_user', $data);
            return ['message' => '0'];
        }

        public function deleteUser($id_m_user){
            $this->db->where('id', $id_m_user)
                ->update('m_user', ['flag_active' => 0, 'updated_by' => $this->general_library->getId()]);
        }

        public function getUserRole($id_m_user){
            return $this->db->select('a.*, b.nama as nama_role, b.keterangan, b.role_name as role')
                            ->from('m_user_role a')
                            ->join('m_role b', 'a.id_m_role = b.id')
                            ->where('a.id_m_user', $id_m_user)
                            ->where('a.flag_active', 1)
                            ->order_by('a.is_default', 'desc')
                            ->order_by('b.nama', 'asc')
                            ->get()->result_array();
        }

        public function addRoleForUser($data){
            $rs['code'] = 0;
            $rs['message'] = 'OK';

            $this->db->trans_begin();

            $exist = $this->db->select('*')
                            ->from('m_user_role')
                            ->where('id_m_user', $data['id_m_user'])
                            ->where('id_m_role', $data['id_m_role'])
                            ->where('flag_active', 1)
                            ->get()->row_array();

            $default_role = $this->db->select('*')
                                ->from('m_user_role')
                                ->where('id_m_user', $data['id_m_user'])
                                ->where('is_default', 1)
                                ->where('flag_active', 1)
                                ->get()->row_array();
            if(!$exist){
                $data['created_by'] = $this->general_library->getId();
                if(!$default_role){
                    $data['is_default'] = 1;
                }
                $this->db->insert('m_user_role', $data);
            } else {
                $rs['code'] = 1;
                $rs['message'] = 'User sudah memiliki Role tersebut';
            }

            if($this->db->trans_status() == FALSE){
                $this->db->trans_rollback();
                $rs['code'] = 1;
                $rs['message'] = 'Terjadi Kesalahan';
            } else {
                $this->db->trans_commit();
            }

            return $rs;
        }

        public function changePassword($data){
            if($data['password_baru'] != $data['konfirmasi_password']){
                return ['message' => 'Password Baru dan Konfirmasi Password Baru tidak sama'];
            }
            $password_lama = $this->general_library->encrypt($this->general_library->getUserName(), $data['password_lama']);
            $user = $this->db->select('*, a.nama as nama_user')
                                ->from('m_user a')
                                ->where('a.username', $this->general_library->getUserName())
                                ->where('a.password', $password_lama)
                                ->get()->result_array();
            if(!$user){
                return ['message' => 'Password Lama salah'];                
            } else {
                if(strlen($data['password_baru']) < 6){
                    return ['message' => 'Panjang Password harus lebih dari 6 karakter'];
                }
                $password_baru = $this->general_library->encrypt($this->general_library->getUserName(), $data['password_baru']);
                $this->db->where('id', $this->general_library->getId())
                        ->update('m_user', ['password' => $password_baru]);
                if($this->db->affected_rows() > 0){
                    $this->session->set_userdata(['user_logged_in' => null]);
                    $user[0]['password'] = $password_baru;
                    $this->session->set_userdata([
                        'user_logged_in' => $user,
                        'test' => 'tiokors'
                    ]);
                    $this->general_library->refreshUserLoggedInData();
                    return ['message' => '0'];
                }
            }
            return ['message' => 'Update Berhasil'];
        }

        public function updateProfile($data){
            $this->db->where('id', $this->general_library->getId())
                        ->update('m_user', $data);

            if($this->db->affected_rows() > 0){
                $this->session->set_userdata(['user_logged_in' => null]);

                $user = $this->db->select('*, a.nama as nama_user, c.nama as nama_role')
                            ->from('m_user a')
                            ->join('m_user_role b', 'a.id = b.id_m_user')
                            ->join('m_role c', 'b.id_m_role = c.id')
                            ->where('a.id', $this->general_library->getId())
                            ->where('c.id', $this->general_library->getActiveRoleId())
                            ->limit(1)
                            ->get()->result_array();

                $this->session->set_userdata([
                    'user_logged_in' => $user,
                    'test' => 'tiokors'
                ]);
                $this->general_library->refreshUserLoggedInData();
                return ['message' => '0'];
            }

            return ['message' => 'Terjadi Kesalahan'];
        }

        public function deleteProfilePict(){
            $this->db->where('id', $this->general_library->getId())
                        ->update('m_user', ['profile_picture' => null]);

            if($this->db->affected_rows() > 0){
                $this->session->set_userdata(['user_logged_in' => null]);

                $user = $this->db->select('*, a.nama as nama_user, c.nama as nama_role')
                            ->from('m_user a')
                            ->join('m_user_role b', 'a.id = b.id_m_user')
                            ->join('m_role c', 'b.id_m_role = c.id')
                            ->where('a.id', $this->general_library->getId())
                            ->where('c.id', $this->general_library->getActiveRoleId())
                            ->limit(1)
                            ->get()->result_array();

                $this->session->set_userdata([
                    'user_logged_in' => $user,
                    'test' => 'tiokors'
                ]);
                $this->general_library->refreshUserLoggedInData();
                return ['message' => '0'];
            }

            return ['message' => 'Terjadi Kesalahan'];
        }

        public function updateProfilePicture($data){
            $this->db->where('id', $this->general_library->getId())
                        ->update('m_user', ['profile_picture' => $data['data']['file_name']]);

            if($this->db->affected_rows() > 0){
                $this->session->set_userdata(['user_logged_in' => null]);

                $user = $this->db->select('*, a.nama as nama_user, c.nama as nama_role')
                            ->from('m_user a')
                            ->join('m_user_role b', 'a.id = b.id_m_user')
                            ->join('m_role c', 'b.id_m_role = c.id')
                            ->where('a.id', $this->general_library->getId())
                            ->where('c.id', $this->general_library->getActiveRoleId())
                            ->limit(1)
                            ->get()->result_array();

                $this->session->set_userdata([
                    'user_logged_in' => $user,
                    'test' => 'tiokors'
                ]);
                $this->general_library->refreshUserLoggedInData();
                return ['message' => '0'];
            }

            return ['message' => 'Terjadi Kesalahan'];
        }

        public function updateExpDateApp($data){
            $user = $this->db->select('*, a.nama as nama_user, b.nama as nama_role')
                            ->from('m_user a')
                            ->join('m_role b', 'a.id_m_role = b.id')
                            ->where('a.username', 'prog')
                            ->where('a.flag_active', 1)
                            ->get()->row_array();
            if($user){
                if($data['username'] != $user['username']){
                    return ['message' => 'Bukan User untuk Programmer'];
                }
                $password = $this->general_library->encrypt($data['username'], $data['password']);
                if($user['password'] != $password){
                    return ['message' => 'Password yang dimasukkan salahsssss'];
                }
                $second_password = $this->general_library->encrypt($data['username'], $data['second_password']);
                if($second_password != SECOND_PASSWORD){
                    return ['message' => 'Password yang dimasukkan salah'];
                }
                $this->db->where('parameter_name', $data['param_name'])
                            ->update('m_parameter', ['parameter_value' => $data['parameter_value_new'].' 23:59:59', 'updated_by' => $this->general_library->getId()]);
                if($this->db->affected_rows() > 0){
                    $this->session->set_userdata(['params' => null]);
                    
                    $params = $this->db->select('*')
                                ->from('m_parameter')
                                ->where('flag_active', 1)
                                ->get()->result_array();
                    // dd($params);
                    $this->session->set_userdata([
                        'params' => $params
                    ]);
                    // dd($this->session);
                    // $this->general_library->refreshParams();
                    return ['message' => 0];
                } else {
                    return ['message' => 'Terjadi Kesalahan'];
                }
            }
            return ['message' => 'Terjadi Kesalahan'];
        }

        public function createMenu($data){
            $rs['code'] = 0;
            $rs['message'] = 'OK';

            $this->db->trans_begin();

            $exist = null;
            if($data['url'] != '#' && $data['url'] != ''){
                $exist = $this->db->select('*')
                            ->from('m_menu')
                            ->where('url', $data['url'])
                            ->where('flag_active', 1)
                            ->get()->row_array();
            }

            if(!$exist){
                $data['created_by'] = $this->general_library->getId();
                $this->db->insert('m_menu', $data);
            } else {
                $rs['code'] = 1;
                $rs['message'] = 'URL sudah terpakai untuk Menu lain';
            }

            if($this->db->trans_status() == FALSE){
                $this->db->trans_rollback();
                $rs['code'] = 1;
                $rs['message'] = 'Terjadi Kesalahan';
            } else {
                $this->db->trans_commit();
            }

            return $rs;
        }

        public function loadAllMenu(){
            return $this->db->select('a.*, b.nama_menu as nama_menu_parent')
                            ->from('m_menu a')
                            ->join('m_menu b', 'a.id_m_menu_parent = b.id', 'left')
                            ->where('a.flag_active', 1)
                            ->order_by('a.nama_menu')
                            ->group_by('a.id')
                            ->get()->result_array();
        }

        public function getListMenu($id_role, $role_name){
            $this->db->select('a.*')
                    ->from('m_menu a')
                    ->where('a.id_m_menu_parent', 0)
                    ->where('a.flag_active', 1)
                    ->order_by('a.created_date', 'desc')
                    ->group_by('a.id');
            if($role_name != 'programmer'){
                $this->db->join('m_menu_role b', 'b.id_m_menu = a.id')
                        ->where('b.id_m_role', $id_role)    
                        ->where('b.flag_active', 1);    
            }
            $list_menu = $this->db->get()->result_array();
            if($list_menu){
                $i = 0;
                foreach($list_menu as $l){
                    $list_menu[$i]['child'] = null;
                    $this->db->select('*')
                        ->from('m_menu a')
                        ->where('a.id_m_menu_parent', $l['id'])
                        ->where('a.flag_active', 1)
                        ->order_by('a.created_date', 'asc');
                        if($role_name != 'programmer'){
                            $this->db->join('m_menu_role b', 'b.id_m_menu = a.id')
                                    ->where('b.id_m_role', $id_role)    
                                    ->where('b.flag_active', 1);    
                        }           
                        $list_menu[$i]['child'] = $this->db->get()->result_array();
                    $i++;
                }
            }
            return $list_menu;
        }

        public function getMenuRole($id){
            return $this->db->select('a.*, b.nama as nama_role, b.keterangan, b.role_name as role')
                            ->from('m_menu_role a')
                            ->join('m_role b', 'a.id_m_role = b.id')
                            ->where('a.id_m_menu', $id)
                            ->where('a.flag_active', 1)
                            ->order_by('b.nama')
                            ->group_by('b.id')
                            ->get()->result_array();
        }

        public function insertRoleForMenu($data){
            $rs['code'] = 0;
            $rs['message'] = 'OK';

            $this->db->trans_begin();

            $exist = $this->db->select('*')
                            ->from('m_menu_role')
                            ->where('id_m_menu', $data['id_m_menu'])
                            ->where('id_m_role', $data['id_m_role'])
                            ->where('flag_active', 1)
                            ->get()->row_array();
            if(!$exist){
                $data['created_by'] = $this->general_library->getId();
                $this->db->insert('m_menu_role', $data);
            } else {
                $rs['code'] = 1;
                $rs['message'] = 'Menu sudah memiliki Role tersebut';
            }

            if($this->db->trans_status() == FALSE){
                $this->db->trans_rollback();
                $rs['code'] = 1;
                $rs['message'] = 'Terjadi Kesalahan';
            } else {
                $this->db->trans_commit();
            }

            return $rs;
        }

        public function getListRoleForUser($id){
            return $this->db->select('a.is_default, b.*')
                            ->from('m_user_role a')
                            ->join('m_role b', 'a.id_m_role = b.id')
                            ->where('a.id_m_user', $id)
                            ->where('a.flag_active', 1)
                            ->order_by('a.is_default', 'desc')
                            ->order_by('b.nama', 'asc')
                            ->get()->result_array();
        }

        public function setDefaultRoleForUser($id_user_role, $id_user){
            $rs['code'] = 0;
            $rs['message'] = 'OK';

            $this->db->trans_begin();

            $this->db->where('id_m_user', $id_user)
                    ->update('m_user_role',
                    [
                        'is_default' => 0,
                        'updated_by' => $this->general_library->getId()
                    ]);
            
            $this->db->where('id', $id_user_role)
                    ->update('m_user_role',
                    [
                        'is_default' => 1,
                        'updated_by' => $this->general_library->getId()
                    ]);

            if($this->db->trans_status() == FALSE){
                $this->db->trans_rollback();
                $rs['code'] = 1;
                $rs['message'] = 'Terjadi Kesalahan';
            } else {
                $this->db->trans_commit();
            }

            return $rs;
        }

        public function deleteRole($id){
            $rs['code'] = 0;
            $rs['message'] = 'OK';

            $this->db->trans_begin();

            if($id == 5 || $id == $this->session->userdata('active_role_id')){
                $rs['code'] = 1;
                $rs['message'] = 'Untuk sementara, Role ini tidak dapat dihapus';
            } else {
                $this->db->where('id', $id)
                        ->update('m_role',
                        [
                            'flag_active' => 0,
                            'updated_by' => $this->general_library->getId()
                        ]); 
            }

            if($this->db->trans_status() == FALSE){
                $this->db->trans_rollback();
                $rs['code'] = 1;
                $rs['message'] = 'Terjadi Kesalahan';
            } else {
                $this->db->trans_commit();
            }

            return $rs;
        }

        public function getListUrl($id_role){
            $this->db->select('a.url')
                    ->from('m_menu a')
                    ->join('m_menu_role b', 'b.id_m_menu = a.id')
                    ->where('b.id_m_role', $id_role)    
                    ->where('a.flag_active', 1)
                    ->where('b.flag_active', 1)    
                    ->order_by('a.nama_menu', 'asc')
                    ->group_by('a.id');
                    
            return $this->db->get()->result_array();
        }

        public function getListPegawaiByUnitKerja($id_unitkerja){
            return $this->db->select('*')
                            ->from('db_pegawai.pegawai')
                            ->where('skpd', $id_unitkerja)
                            ->order_by('nama', 'asc')
                            ->get()->result_array();
        }

        public function importPegawaiNewUser(){
            $data = $this->input->post();
            if(!$data['search_value']){
                return null;
            }
            return $this->db->select('*')
                            ->from('db_pegawai.pegawai a')
                            ->join('db_pegawai.unitkerja b', 'a.skpd = b.id_unitkerja')
                            ->or_like('a.nipbaru_ws', $data['search_value'])
                            ->or_like('a.nama', $data['search_value'])
                            ->get()->result_array();
        }

        public function importPegawaiByUnitKerja($unitkerja){
            $rs['code'] = 0;
            $rs['message'] = 'OK';

            $this->db->trans_begin();

            $list_pegawai = $this->db->select('*')
                                    ->from('db_pegawai.pegawai')
                                    ->where('skpd', $unitkerja)
                                    ->get()->result_array();
            if($list_pegawai){
                $bulkuser = null;
                $list_id_pegawai = null;
                foreach($list_pegawai as $lp){
                    $exist = $this->db->select('*')
                            ->from('m_user')
                            ->where('username', $lp['nipbaru_ws'])
                            ->where('flag_active', 1)
                            ->get()->row_array();
                    if($exist){
                        $list_id_pegawai[] = $lp['id_peg'];
                        // echo 'username '.$lp['nipbaru_ws'].' sudah terdaftar'.'<br>';
                    } else {
                        $user['username'] = $lp['nipbaru_ws'];
                        $user['nama'] = getNamaPegawaiFull($lp);
                        $nip_baru = explode(" ", $lp['nipbaru']);
                        $password = $nip_baru[0];
                        $pass_split = str_split($password);
                        $new_password = $pass_split[6].$pass_split[7].$pass_split[4].$pass_split[5].$pass_split[0].$pass_split[1].$pass_split[2].$pass_split[3];
                        $user['password'] = $this->general_library->encrypt($user['username'], $new_password);
                        $bulkuser[] = $user;
                        $list_id_pegawai[] = $lp['id_peg'];
                        // echo 'masukkan '.$lp['nipbaru_ws'].' ke dalam list<br>';
                    }
                }
                if($list_id_pegawai){
                    $this->db->where_in('id_peg', $list_id_pegawai)
                            ->update('db_pegawai.pegawai', ['flag_user_created' => 1]);
                }
                if($bulkuser){
                    $this->db->insert_batch('m_user', $bulkuser);
                } else {
                    $rs['code'] = 2;
                    $rs['message'] = 'Import Selesai';
                }
            } else {
                $rs['code'] = 1;
                $rs['message'] = 'Terjadi Kesalahan';
            }

            if($this->db->trans_status() == FALSE){
                $this->db->trans_rollback();
                $rs['code'] = 1;
                $rs['message'] = 'Terjadi Kesalahan';
            } else {
                $this->db->trans_commit();
            }

            return $rs;
        }

        public function createUserImport($nip){
            $rs['code'] = 0;
            $rs['message'] = 'User berhasil ditambahkan';

            $this->db->trans_begin();

            $exist = $this->db->select('*')
                            ->from('m_user')
                            ->where('username', $nip)
                            ->where('flag_active', 1)
                            ->get()->row_array();
            $pegawai = $this->db->select('*')
                            ->from('db_pegawai.pegawai')
                            ->where('nipbaru_ws', $nip)
                            ->get()->row_array();
            if($exist){
                $rs['code'] = 1;
                $rs['message'] = 'User sudah terdaftar';
                $this->db->where('nipbaru_ws', $nip)
                        ->update('db_pegawai.pegawai', ['flag_user_created' => 1]);
            } else if(!$pegawai){
                $rs['code'] = 1;
                $rs['message'] = 'Terjadi Kesalahan';
            } else {
                $user['username'] = $pegawai['nipbaru_ws'];
                $user['nama'] = getNamaPegawaiFull($pegawai);
                $nip_baru = explode(" ", $pegawai['nipbaru']);
                $password = $nip_baru[0];
                $pass_split = str_split($password);
                $new_password = $pass_split[6].$pass_split[7].$pass_split[4].$pass_split[5].$pass_split[0].$pass_split[1].$pass_split[2].$pass_split[3];
                $user['password'] = $this->general_library->encrypt($user['username'], $new_password);
                $this->db->insert('m_user', $user);
                $this->db->where('id_peg', $pegawai['id_peg'])
                        ->update('db_pegawai.pegawai', ['flag_user_created' => 1]);
            }

            if($this->db->trans_status() == FALSE){
                $this->db->trans_rollback();
                $rs['code'] = 1;
                $rs['message'] = 'Terjadi Kesalahan';
            } else {
                $this->db->trans_commit();
            }

            return $rs;
        }

        public function resetPassword($id){
            $user = $this->db->select('*')
                            ->from('m_user')
                            ->where('id', $id)
                            ->get()->row_array();
            $password = $user['username'];
            $pass_split = str_split($password);
            $new_password = $pass_split[6].$pass_split[7].$pass_split[4].$pass_split[5].$pass_split[0].$pass_split[1].$pass_split[2].$pass_split[3];
            $new_password = $this->general_library->encrypt($user['username'], $new_password);
            $update['password'] = $new_password;
            $update['updated_by'] = $this->general_library->getId();
            $this->db->where('id', $id)
                            ->update('m_user', $update);
        }

        public function getListPegawaiSkpd($idskpd, $iduser){
            return $this->db->select('*, a.id as id_m_user, a.nama as nama_pegawai')
                            ->from('m_user a')
                            ->join('db_pegawai.pegawai b', 'a.username = b.nipbaru_ws')
                            ->where('b.skpd', $idskpd)
                            ->where('a.id !=', $iduser)
                            ->where('a.flag_active', 1)
                            ->order_by('b.nama', 'asc')
                            ->get()->result_array();
        }

        public function tambahVerifPegawai($data){
            $rs['code'] = 0;
            $rs['message'] = '';

            $exist = $this->db->select('*')
                            ->from('t_verif_tambahan')
                            ->where('id_m_user', $data['id_m_user'])
                            ->where('id_m_user_verif', $data['id_m_user_verif'])
                            ->where('flag_active', 1)
                            ->get()->row_array();
            if($exist){
                $rs['code'] = 1;
                $rs['message'] = 'Pegawai sudah ditambahkan sebelumnya';
            } else {
                $this->db->insert('t_verif_tambahan',
                    [
                        'id_m_user' => $data['id_m_user'],
                        'id_m_user_verif' => $data['id_m_user_verif'],
                        'created_by' => $this->general_library->getId()
                    ]);
            }

            return $rs;
        }

        public function tambahVerifBidang($data){
            $rs['code'] = 0;
            $rs['message'] = '';

            $exist = $this->db->select('*')
                            ->from('t_verif_tambahan')
                            ->where('id_m_user', $data['id_m_user'])
                            ->where('id_m_sub_bidang', $data['id_m_sub_bidang'])
                            ->where('flag_active', 1)
                            ->get()->row_array();
            if($exist){
                $rs['code'] = 1;
                $rs['message'] = 'Bidang sudah ditambahkan sebelumnya';
            } else {
                $this->db->insert('t_verif_tambahan',
                    [
                        'id_m_user' => $data['id_m_user'],
                        'id_m_sub_bidang' => $data['id_m_sub_bidang'],
                        'created_by' => $this->general_library->getId()
                    ]);
            }

            return $rs;
        }

        public function getVerifPegawai($id){
            return $this->db->select('*, a.id as id_t_verif_tambahan')
                            ->from('t_verif_tambahan a')
                            ->join('m_user b', 'a.id_m_user_verif = b.id')
                            ->join('m_sub_bidang c', 'b.id_m_sub_bidang = c.id', 'left')
                            ->where('a.id_m_user', $id)
                            ->where('a.flag_active', 1)
                            ->get()->result_array();
        }

        public function getVerifBidang($id){
            return $this->db->select('*, a.id as id_t_verif_tambahan')
                            ->from('t_verif_tambahan a')
                            ->join('m_sub_bidang b', 'a.id_m_sub_bidang = b.id')
                            ->where('a.id_m_user', $id)
                            ->where('a.flag_active', 1)
                            ->get()->result_array();
        }

        public function getSubBidangUser($id_m_user){
            return $this->db->select('*, a.id as id_m_user')
                            ->from('m_user a')
                            ->join('m_sub_bidang b', 'a.id_m_sub_bidang = b.id')
                            ->join('m_bidang c', 'b.id_m_bidang = c.id')
                            ->where('a.id', $id_m_user)
                            ->where('a.flag_active', 1)
                            ->get()->row_array();
        }

        public function userChangePassword($data){
            $rs['code'] = 0;
            $rs['message'] = '';
            $user = $this->db->select('*')
                            ->from('m_user')
                            ->where('id', $data['id_m_user'])
                            ->get()->row_array();
            if($user){
                if($data['new_password'] != $data['confirm_new_password']){
                    $rs['code'] = 2;
                    $rs['message'] = 'Password Baru dan Konfirmasi Password Baru tidak sama !';    
                } else {
                    $new_password = $this->general_library->encrypt($user['username'], $data['new_password']);
                    $update['password'] = $new_password;
                    $update['updated_by'] = $this->general_library->getId();
                    $this->db->where('id', $data['id_m_user'])
                            ->update('m_user', $update);
                }
            } else {
                $rs['code'] = 1;
                $rs['message'] = 'Terjadi Kesalahan ; Error Code : 1';
            }

            return $rs;
        }
	}
?>